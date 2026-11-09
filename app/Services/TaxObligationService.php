<?php

namespace App\Services;

use App\Models\Company;
use App\Models\TaxSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaxObligationService
{
    public function createSchedules(Company $company, array $data): Collection
    {
        return DB::transaction(function () use ($company, $data) {
            $this->lockCompany($company);
            $data['request_id'] = strtolower($data['request_id']);
            $data['request_fingerprint'] = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = TaxSchedule::where('company_id', $company->id)->where('request_id', $data['request_id'])->get();

            if ($existing->isNotEmpty()) {
                abort_unless($existing->first()->request_fingerprint === $data['request_fingerprint'], 409, 'Ovaj zahtev je već iskorišćen za drugi raspored.');

                return $existing;
            }

            $calendar = $this->calendar($company);
            $start = CarbonImmutable::parse($data['starts_on']);

            return collect($data['items'])->map(function (array $item) use ($company, $data, $calendar, $start) {
                $schedule = TaxSchedule::create([
                    ...$data,
                    'company_id' => $company->id,
                    'category' => $item['category'],
                    'name' => $item['name'],
                ]);
                $schedule->versions()->create([
                    'effective_on' => $schedule->starts_on,
                    'amount_minor' => $item['amount_minor'],
                    'payment_details' => $item['payment_details'],
                ]);
                $this->generateSchedule($schedule, $start->year, $calendar);

                return $schedule;
            });
        }, 3);
    }

    public function generate(Company $company, int $year): int
    {
        return DB::transaction(function () use ($company, $year) {
            $this->lockCompany($company);
            $calendar = $this->calendar($company);

            return TaxSchedule::where('company_id', $company->id)
                ->where('starts_on', '<=', "$year-12-31")
                ->with('versions')
                ->get()
                ->sum(fn (TaxSchedule $schedule) => $this->generateSchedule($schedule, $year, $calendar));
        }, 3);
    }

    public function lockCompany(Company $company): void
    {
        Company::query()->lockForUpdate()->findOrFail($company->id);
    }

    public function calendar(Company $company): array
    {
        return DB::table('tax_non_working_days')->where('company_id', $company->id)->orderBy('day')->pluck('day')->all();
    }

    private function generateSchedule(TaxSchedule $schedule, int $year, array $calendar): int
    {
        $start = CarbonImmutable::parse($schedule->starts_on);
        $months = match ($schedule->frequency) {
            'quarterly' => 3,
            'yearly' => 12,
            default => 1,
        };
        $limit = $schedule->frequency === 'once' ? 1 : $schedule->period_count;
        $firstPeriod = max(0, (int) ceil((($year - $start->year) * 12 + 1 - $start->month) / $months));
        $versions = $schedule->versions->sortByDesc('effective_on');
        $daysOff = array_fill_keys($calendar, true);
        $existing = $schedule->obligations()
            ->whereBetween('period_start', ["$year-01-01", "$year-12-31"])
            ->pluck('period_start')->flip();
        $created = 0;

        for ($index = $firstPeriod; $limit === null || $index < $limit; $index++) {
            $month = $start->startOfMonth()->addMonths($index * $months);
            if ($month->year !== $year) {
                break;
            }

            $periodStart = $index === 0 ? $start : $month;
            if ($existing->has($periodStart->toDateString())) {
                continue;
            }

            $once = $schedule->frequency === 'once';
            $periodEnd = $once ? $periodStart : $month->addMonths($months - 1)->endOfMonth();
            $dueMonth = $periodEnd->startOfMonth()->addMonths($schedule->due_month_offset ?? 0);
            $due = $once ? CarbonImmutable::parse($schedule->due_on)
                : $dueMonth->day(min($schedule->due_day, $dueMonth->daysInMonth))->max($periodStart);

            if ($schedule->roll_forward) {
                while ($due->isWeekend() || isset($daysOff[$due->toDateString()])) {
                    $due = $due->addDay();
                }
            }

            $version = $versions->first(fn ($version) => $version->effective_on <= $periodStart->toDateString());
            $schedule->obligations()->create([
                'company_id' => $schedule->company_id,
                'tax_schedule_version_id' => $version->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_on' => $due->toDateString(),
                'remind_on' => $schedule->reminder_days === null ? null : $due->subDays($schedule->reminder_days)->toDateString(),
                'amount_minor' => $version->amount_minor,
            ]);
            $created++;
        }

        return $created;
    }
}
