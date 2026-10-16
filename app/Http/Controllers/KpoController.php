<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateKpoEntriesRequest;
use App\Http\Requests\KpoGenerationPreviewRequest;
use App\Http\Requests\KpoPdfRequest;
use App\Http\Requests\StoreKpoEntryRequest;
use App\Http\Requests\UpdateKpoEntryRequest;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Services\KpoEntryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class KpoController extends Controller
{
    public function index(Request $request)
    {
        $company = auth()->user()->activeCompany;
        if (! $company) {
            return response()->json(['message' => 'Korisnik nema aktivnu firmu.'], 422);
        }
        $this->authorize('view', $company);

        $entries = $this->kpoQuery($company, $request->all())->get();

        return response()->json([
            'entries' => $entries,
            'totals' => [
                'amount_rsd' => round((float) $entries->sum('amount_rsd'), 2),
                'adjustment_rsd' => round((float) $entries->sum('adjustment_rsd'), 2),
                'total_rsd' => round((float) $entries->sum('total_rsd'), 2),
            ],
        ]);
    }

    public function store(StoreKpoEntryRequest $request, KpoEntryService $service)
    {
        $company = auth()->user()->activeCompany;
        if (! $company) {
            return response()->json(['message' => 'Korisnik nema aktivnu firmu.'], 422);
        }
        $this->authorize('view', $company);

        $entry = $service->create($company, $request->validated());

        return response()->json($entry->load('sourceInvoice'), 201);
    }

    public function generationPreview(KpoGenerationPreviewRequest $request, KpoEntryService $service)
    {
        $company = auth()->user()->activeCompany;
        if (! $company) {
            return response()->json(['message' => 'Korisnik nema aktivnu firmu.'], 422);
        }
        $this->authorize('view', $company);

        $year = (int) $request->validated('year');
        $includeHistorical = filter_var(
            $request->validated('include_historical', false),
            FILTER_VALIDATE_BOOLEAN
        );
        return response()->json($service->previewFromInvoices($company, $year, $includeHistorical));
    }

    public function generate(GenerateKpoEntriesRequest $request, KpoEntryService $service)
    {
        $company = auth()->user()->activeCompany;
        if (! $company) {
            return response()->json(['message' => 'Korisnik nema aktivnu firmu.'], 422);
        }
        $this->authorize('view', $company);

        $validated = $request->validated();
        $result = $service->generateFromInvoices(
            $company,
            (int) $validated['year'],
            $validated['entries'],
            (bool) ($validated['confirm_manual_overlap'] ?? false),
            (bool) ($validated['include_historical'] ?? false)
        );

        return response()->json($result);
    }

    public function show(KpoEntry $kpo)
    {
        $this->authorize('view', $kpo);

        return response()->json($kpo->load('sourceInvoice'));
    }

    public function update(UpdateKpoEntryRequest $request, KpoEntry $kpo, KpoEntryService $service)
    {
        $this->authorize('update', $kpo);
        $entry = $service->update($kpo, $request->validated());

        return response()->json($entry->load('sourceInvoice'));
    }

    public function generatePDF(KpoPdfRequest $request)
    {
        $company = auth()->user()->activeCompany;
        if (! $company) {
            return response()->json(['message' => 'Korisnik nema aktivnu firmu.'], 422);
        }

        $this->authorize('view', $company);

        $filters = $request->validated();
        $entries = $this->kpoQuery($company, $filters)->get();
        $totals = [
            'amount_rsd' => round((float) $entries->sum('amount_rsd'), 2),
            'adjustment_rsd' => round((float) $entries->sum('adjustment_rsd'), 2),
            'total_rsd' => round((float) $entries->sum('total_rsd'), 2),
        ];

        $period = $this->pdfPeriod($filters);
        $relativePath = "kpo/{$period['folder']}/kpo-evidencija-{$company->id}-{$period['key']}.pdf";

        Storage::disk('local')->makeDirectory(dirname($relativePath));
        Pdf::html($this->renderKpoPdfHtml($company, $entries, $totals, $period['label']))
            ->format(Format::A4)
            ->withBrowsershot(fn (Browsershot $browsershot) => $browsershot->setOption('args', ['--no-sandbox']))
            ->disk('local')
            ->save($relativePath);

        return Storage::disk('local')->download($relativePath, "kpo-evidencija-{$period['key']}.pdf");
    }

    private function kpoQuery(Company $company, array $filters): Builder
    {
        return KpoEntry::query()
            ->forCompany($company->id)
            ->with('sourceInvoice')
            ->when(isset($filters['year']) && $filters['year'] !== '', fn (Builder $query) => $query->forYear((int) $filters['year']))
            ->when(! empty($filters['from']), fn (Builder $query) => $query->whereDate('entry_date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn (Builder $query) => $query->whereDate('entry_date', '<=', $filters['to']))
            ->orderByDesc('entry_date')
            ->orderByDesc('entry_number');
    }

    private function pdfPeriod(array $filters): array
    {
        if (! empty($filters['year'])) {
            return ['folder' => (string) $filters['year'], 'key' => (string) $filters['year'], 'label' => 'Godina '.$filters['year']];
        }

        $from = ! empty($filters['from']) ? Carbon::parse($filters['from']) : null;
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to']) : null;
        $key = $from || $to
            ? ($from?->format('Y-m-d') ?? 'pocetak').'-do-'.($to?->format('Y-m-d') ?? 'kraj')
            : 'svi-periodi';

        return [
            'folder' => $from?->format('Y') ?? $to?->format('Y') ?? 'svi-periodi',
            'key' => $key,
            'label' => $from || $to
                ? ($from?->format('d.m.Y') ?? 'Početak').' - '.($to?->format('d.m.Y') ?? 'Kraj')
                : 'Svi periodi',
        ];
    }

    private function renderKpoPdfHtml(Company $company, $entries, array $totals, string $periodLabel): string
    {
        $rows = $entries->map(function (KpoEntry $entry): string {
            $product = $this->isProductIncome($entry->income_type) ? $entry->total_rsd : null;
            $service = $this->isServiceIncome($entry->income_type) ? $entry->total_rsd : null;

            return '<tr>'
                .'<td class="number">'.e($entry->entry_number).'</td>'
                .'<td class="date-description"><span class="entry-date">'.e($entry->entry_date?->format('d.m.Y')).'</span><br><span class="entry-description">'.e($entry->description ?: 'Bez opisa').'</span></td>'
                .'<td class="product-amount">'.$this->formatPdfAmount($product).'</td>'
                .'<td class="service-amount">'.$this->formatPdfAmount($service).'</td>'
                .'<td class="total-amount">'.$this->formatPdfAmount($entry->total_rsd).'</td>'
                .'</tr>';
        })->implode('');

        $companyDetails = collect([
            $company->tax_id ? 'PIB: '.$company->tax_id : null,
            $company->registration_number ? 'Matični broj: '.$company->registration_number : null,
            $company->address,
            $company->city,
        ])->filter()->map(fn (string $value) => e($value))->implode(' · ');

        return '<!doctype html><html lang="sr-Latn"><head><meta charset="UTF-8"><style>'
            .'@page{size:A4;margin:16mm 11mm 18mm}*{box-sizing:border-box}body{font-family:Arial,"DejaVu Sans",sans-serif;color:#172033;font-size:9px;margin:0}h1{font-size:18px;margin:0 0 4px}h2{font-size:11px;font-weight:normal;color:#536174;margin:0 0 8px}.company{font-size:10px;font-weight:bold}.details{font-size:8px;color:#536174;margin-top:3px}.meta{border-top:1px solid #d8dee8;border-bottom:1px solid #d8dee8;margin:12px 0;padding:7px 0;display:flex;justify-content:space-between}.meta strong{font-size:9px}table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}th{background:#eef2f7;color:#293449;font-size:8px;text-align:left;padding:6px 5px;border:1px solid #d8dee8}td{padding:6px 5px;border:1px solid #d8dee8;vertical-align:top;overflow-wrap:anywhere;word-break:break-word}tr{page-break-inside:avoid}.number{width:8%;text-align:center}.date-description{width:52%}.product-amount{width:13%;text-align:right;white-space:nowrap}.service-amount{width:13%;text-align:right;white-space:nowrap}.total-amount{width:14%;text-align:right;white-space:nowrap}.entry-date{white-space:nowrap}.entry-description{overflow-wrap:anywhere;word-break:break-word}.amount{ text-align:right;white-space:nowrap}.summary{margin-top:12px;width:48%;margin-left:auto}.summary td{padding:5px}.summary .total td{font-weight:bold;background:#eef2f7}.empty{text-align:center;color:#536174;padding:18px}.footer{position:fixed;bottom:-10mm;left:0;right:0;text-align:center;color:#7a8494;font-size:8px}.pageNumber:after{content:counter(page)}'
            .'</style></head><body><div class="company">'.e($company->name).'</div><div class="details">'.$companyDetails.'</div><h1>KPO evidencija</h1><h2>Poslovna knjiga o ostvarenom prometu - '.e($periodLabel).'</h2><div class="meta"><span>Valuta: <strong>RSD</strong></span><span>Broj zapisa: <strong>'.e((string) $entries->count()).'</strong></span></div><table class="kpo-table"><thead><tr><th class="number">R.br.</th><th class="date-description">Datum i opis knjiženja</th><th class="product-amount">Prihod od proizvoda</th><th class="service-amount">Prihod od usluga</th><th class="total-amount">Ukupan prihod</th></tr></thead><tbody>'.($rows ?: '<tr><td colspan="5" class="empty">Nema zapisa za izabrani period.</td></tr>').'</tbody></table><table class="summary"><tbody><tr><td>Osnovni iznos</td><td class="amount">'.$this->formatPdfAmount($totals['amount_rsd']).'</td></tr><tr><td>Korekcije</td><td class="amount">'.$this->formatPdfAmount($totals['adjustment_rsd']).'</td></tr><tr class="total"><td>Ukupan prihod</td><td class="amount">'.$this->formatPdfAmount($totals['total_rsd']).'</td></tr></tbody></table><div class="footer">KPO evidencija · '.e($periodLabel).' · strana <span class="pageNumber"></span></div></body></html>';
    }

    private function formatPdfAmount(null|int|float|string $amount): string
    {
        return $amount === null ? '&mdash;' : e(number_format((float) $amount, 2, ',', '.').' RSD');
    }

    private function isProductIncome(?string $incomeType): bool
    {
        $incomeType = mb_strtolower((string) $incomeType);

        return str_contains($incomeType, 'proiz') || str_contains($incomeType, 'product');
    }

    private function isServiceIncome(?string $incomeType): bool
    {
        $incomeType = mb_strtolower((string) $incomeType);

        return str_contains($incomeType, 'uslug') || str_contains($incomeType, 'servic');
    }
}
