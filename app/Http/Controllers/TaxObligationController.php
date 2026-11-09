<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxScheduleRequest;
use App\Models\Company;
use App\Models\TaxAttachment;
use App\Models\TaxObligation;
use App\Models\TaxSchedule;
use App\Services\IpsQrService;
use App\Services\TaxObligationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TaxObligationController extends Controller
{
    public function __construct(private TaxObligationService $taxes) {}

    public function index(Request $request, int $company): JsonResponse
    {
        $active = $this->company($request, $company);
        $today = now($active->effectiveTimezone())->toDateString();
        $year = (int) $request->validate(['year' => ['required', 'integer', 'between:2000,2100']])['year'];
        $schedules = TaxSchedule::where('company_id', $company)->with(['versions' => fn ($query) => $query->orderBy('effective_on'), 'attachments'])
            ->orderBy('name')->get();
        $rows = $this->obligations($active)->with('schedule:id,name,category')
            ->whereBetween('period_start', ["$year-01-01", "$year-12-31"])
            ->orderBy('due_on')->orderBy('id')->get();
        $reminders = $this->obligations($active)->with('schedule:id,name,category')
            ->where('remind_on', '<=', $today)->where('due_on', '>=', $today)
            ->orderBy('due_on')->get()->filter(fn (TaxObligation $row) => $row->outstanding() !== 0)->values();
        $totals = [
            'assessed_minor' => $rows->sum('amount_minor'),
            'paid_minor' => $rows->sum('payments_sum_amount_minor'),
            'outstanding_minor' => $rows->sum(fn (TaxObligation $row) => $row->outstanding() ?? 0),
            'overdue_minor' => $rows->where('due_on', '<', $today)->sum(fn (TaxObligation $row) => $row->outstanding() ?? 0),
            'unassessed_count' => $rows->whereNull('amount_minor')->count(),
        ];

        return response()->json([
            'today' => $today,
            'categories' => collect(TaxSchedule::CATEGORIES)->merge($schedules->pluck('category'))->unique()->values(),
            'schedules' => $schedules,
            'obligations' => $rows->map(fn (TaxObligation $row) => $this->summary($row)),
            'reminders' => $reminders->map(fn (TaxObligation $row) => $this->summary($row)),
            'totals' => $totals,
            'non_working_days' => $this->taxes->calendar($active),
        ]);
    }

    public function store(StoreTaxScheduleRequest $request, int $company): JsonResponse
    {
        $active = $this->company($request, $company);

        return response()->json($this->taxes->createSchedules($active, $request->validated()), 201);
    }

    public function generate(Request $request, int $company): JsonResponse
    {
        $active = $this->company($request, $company);
        $data = $request->validate(['year' => ['required', 'integer', 'between:2000,2100']]);

        return response()->json(['created' => $this->taxes->generate($active, (int) $data['year'])]);
    }

    public function revise(Request $request, int $company, int $schedule, IpsQrService $ips): JsonResponse
    {
        $active = $this->company($request, $company);
        $plan = $this->schedule($active, $schedule);
        $data = $request->validate([
            'effective_on' => ['required', 'date_format:Y-m-d', 'after:'.$plan->starts_on, 'before_or_equal:2100-12-31'],
            ...StoreTaxScheduleRequest::versionRules(),
        ]);
        $errors = $ips->paymentDetailErrors($data['payment_details'], false);
        if ($errors !== []) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($error, $tag) => ['payment_details.'.$tag => $error])->all());
        }

        $version = DB::transaction(function () use ($active, $plan, $data) {
            $this->taxes->lockCompany($active);
            $existing = $plan->versions()->where('effective_on', $data['effective_on'])->first();

            if ($existing) {
                abort_unless(
                    $existing->amount_minor === ($data['amount_minor'] === null ? null : (int) $data['amount_minor'])
                    && $existing->payment_details == $data['payment_details'],
                    409,
                    'Verzija sa ovim datumom već postoji.',
                );

                return $existing;
            }

            return $plan->versions()->create($data);
        }, 3);

        return response()->json($version, 201);
    }

    public function calendar(Request $request, int $company): JsonResponse
    {
        $active = $this->company($request, $company);
        $data = $request->validate([
            'days' => ['present', 'array', 'list', 'max:2500'],
            'days.*' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2102-12-31', 'distinct'],
        ]);

        DB::transaction(function () use ($active, $data) {
            $this->taxes->lockCompany($active);
            DB::table('tax_non_working_days')->where('company_id', $active->id)->delete();

            foreach (array_chunk($data['days'], 250) as $days) {
                DB::table('tax_non_working_days')->insert(array_map(
                    fn ($day) => ['company_id' => $active->id, 'day' => $day],
                    $days,
                ));
            }
        }, 3);

        return response()->json(['days' => $this->taxes->calendar($active)]);
    }

    public function show(Request $request, int $company, int $obligation, IpsQrService $ips): JsonResponse
    {
        $active = $this->company($request, $company);
        $entry = TaxObligation::where('company_id', $active->id)->with([
            'schedule:id,name,category',
            'version',
            'payments' => fn ($query) => $query->orderBy('paid_on')->orderBy('id'),
            'attachments',
        ])->findOrFail($obligation);
        // Use the same payment rows for the displayed history, balance and QR.
        $entry->payments_sum_amount_minor = $entry->payments->sum('amount_minor');
        $outstanding = $entry->outstanding();
        $result = ['payload' => null, 'png' => null, 'errors' => []];
        if ($outstanding === null) {
            $result['errors'] = ['I' => ['Unesite iznos obaveze za ovaj period.']];
        } elseif ($outstanding > 0) {
            $details = $entry->version->payment_details;
            $standardTax = in_array(strtolower($entry->schedule->category), array_map('strtolower', TaxSchedule::CATEGORIES), true);
            if ($standardTax && empty($details['RO'])) {
                $result['errors'] = array_map(fn ($message) => [$message], $ips->paymentDetailErrors($details));
                $result['errors']['RO'] = ['Unesite model i poziv na broj iz poreskog rešenja.'];
            } else {
                try {
                    $code = $ips->render($details, $outstanding);
                    $result['payload'] = $code['payload'];
                    $result['png'] = 'data:image/png;base64,'.base64_encode($code['png']);
                } catch (ValidationException $error) {
                    $result['errors'] = $error->errors();
                }
            }
        }

        return response()->json([
            ...$this->summary($entry),
            'qr' => $result,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $company, int $obligation): JsonResponse
    {
        $active = $this->company($request, $company);
        $data = $request->validate([
            'amount_minor' => ['sometimes', 'required', 'integer', 'between:0,'.IpsQrService::MAX_AMOUNT_MINOR],
            'note' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);

        DB::transaction(function () use ($active, $obligation, $data) {
            $entry = TaxObligation::where('company_id', $active->id)->lockForUpdate()->findOrFail($obligation);
            if (array_key_exists('amount_minor', $data) && (int) $data['amount_minor'] !== $entry->amount_minor) {
                abort_if(
                    $entry->version->amount_minor !== null || $entry->payments()->exists(),
                    409,
                    'Iznos se može menjati samo kod promenljive obaveze bez evidentiranih uplata.',
                );
            }
            $entry->update($data);
        }, 3);

        return response()->json([], 200);
    }

    public function pay(Request $request, int $company, int $obligation): JsonResponse
    {
        $active = $this->company($request, $company);
        $today = now($active->effectiveTimezone())->toDateString();
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'amount_minor' => ['required', 'integer', 'between:1,'.IpsQrService::MAX_AMOUNT_MINOR],
            'paid_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:'.$today],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);
        $data['request_id'] = strtolower($data['request_id']);

        $payment = DB::transaction(function () use ($active, $obligation, $data) {
            $entry = TaxObligation::where('company_id', $active->id)->lockForUpdate()->findOrFail($obligation);
            $existing = $entry->payments()->where('request_id', $data['request_id'])->first();

            if ($existing) {
                abort_unless(
                    $existing->amount_minor === (int) $data['amount_minor'] && $existing->paid_on === $data['paid_on']
                    && $existing->note === ($data['note'] ?? null),
                    409,
                    'Ovaj zahtev je već iskorišćen za drugu uplatu.',
                );

                return $existing;
            }

            $entry->payments_sum_amount_minor = $entry->payments()->sum('amount_minor');
            $outstanding = $entry->outstanding();
            if ($outstanding === null || (int) $data['amount_minor'] > $outstanding) {
                throw ValidationException::withMessages([
                    'amount_minor' => $outstanding === null ? 'Najpre unesite iznos obaveze.' : 'Uplata prelazi preostali iznos.',
                ]);
            }

            return $entry->payments()->create($data);
        }, 3);

        return response()->json($payment, 201);
    }

    public function attach(Request $request, int $company, int $schedule): JsonResponse
    {
        $active = $this->company($request, $company);
        $plan = $this->schedule($active, $schedule);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
            'obligation_id' => ['nullable', 'integer'],
        ]);
        $obligation = isset($data['obligation_id']) ? $plan->obligations()->where('company_id', $company)->findOrFail($data['obligation_id']) : null;
        $file = $data['file'];
        $path = $file->store("taxes/$company", 'local');
        abort_if($path === false, 500, 'Prilog nije sačuvan.');

        try {
            $attachment = TaxAttachment::create([
                'tax_schedule_id' => $plan->id,
                'tax_obligation_id' => $obligation?->id,
                'name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $path,
            ]);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);

            throw $error;
        }

        return response()->json($attachment, 201);
    }

    public function download(Request $request, int $company, int $schedule, int $attachment): StreamedResponse
    {
        $active = $this->company($request, $company);
        $plan = $this->schedule($active, $schedule);
        $file = TaxAttachment::where('tax_schedule_id', $plan->id)->findOrFail($attachment);
        $storage = Storage::disk('local');
        abort_unless($storage->exists($file->path), 404);

        return $storage->download($file->path, $file->name, ['Cache-Control' => 'private, no-store']);
    }

    private function company(Request $request, int $id): Company
    {
        $user = $request->user();
        abort_unless((int) $user->active_company_id === $id, 404);

        return $user->companies()->findOrFail($id);
    }

    private function schedule(Company $company, int $id): TaxSchedule
    {
        return TaxSchedule::where('company_id', $company->id)->findOrFail($id);
    }

    private function obligations(Company $company): Builder
    {
        return TaxObligation::where('company_id', $company->id)->withSum('payments', 'amount_minor');
    }

    private function summary(TaxObligation $row): array
    {
        return [
            ...$row->toArray(),
            'paid_minor' => (int) $row->payments_sum_amount_minor,
            'outstanding_minor' => $row->outstanding(),
        ];
    }
}
