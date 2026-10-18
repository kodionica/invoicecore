<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;

function timezoneTestContext(string $email, string $timezone): array
{
    $user = User::create([
        'first_name' => 'Timezone',
        'last_name' => 'Tester',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = $user->companies()->create([
        'name' => 'Timezone Company',
        'tax_id' => 'TZ'.random_int(10000000, 99999999),
        'registration_number' => 'TZ'.random_int(100000, 999999),
        'timezone' => $timezone,
        'currency' => 'RSD',
        'vat_enabled' => false,
    ]);
    $client = $company->clients()->create(['name' => 'Timezone Client']);
    $user->update(['active_company_id' => $company->id]);

    return [$user, $company, $client];
}

function timezoneTestInvoice(Company $company, Client $client, string $number, string $dueDate): Invoice
{
    return Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => $number,
        'issue_date' => '2026-10-01',
        'due_date' => $dueDate,
        'currency' => 'RSD',
        'fx_rate_to_rsd' => 1,
        'fx_provider' => 'test',
        'fx_date' => '2026-10-01',
        'total' => 100,
        'subtotal_original' => 100,
        'tax_original' => 0,
        'total_original' => 100,
        'subtotal_rsd' => 100,
        'tax_rsd' => 0,
        'total_rsd' => 100,
        'status' => 'sent',
        'include_in_kpo' => false,
        'vat_enabled_at_issue' => false,
    ]);
}

it('uses the company timezone and falls back for legacy companies', function () {
    [, $company] = timezoneTestContext('timezone-fallback@example.test', Company::DEFAULT_TIMEZONE);
    $company->timezone = null;
    $company->save();

    expect($company->fresh()->effectiveTimezone())->toBe(Company::DEFAULT_TIMEZONE);
});

it('validates and persists an explicitly selected company timezone', function () {
    [$user, $company] = timezoneTestContext('timezone-api@example.test', 'Europe/Belgrade');
    $this->actingAs($user);

    $this->putJson("/api/companies/{$company->id}", ['timezone' => 'America/New_York'])
        ->assertOk()
        ->assertJsonPath('timezone', 'America/New_York');

    $this->putJson("/api/companies/{$company->id}", ['timezone' => 'Not/A-Timezone'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');
});

it('uses each company timezone for overdue dates around midnight', function () {
    Carbon::setTestNow('2026-10-18 23:30:00');
    try {
        [$user, $belgrade, $belgradeClient] = timezoneTestContext('timezone-overdue@example.test', 'Europe/Belgrade');
        $other = $user->companies()->create([
            'name' => 'Other Timezone Company',
            'tax_id' => 'TZ'.random_int(10000000, 99999999),
            'registration_number' => 'TZ'.random_int(100000, 999999),
            'timezone' => 'America/Adak',
            'currency' => 'RSD',
            'vat_enabled' => false,
        ]);
        $otherClient = $other->clients()->create(['name' => 'Other Client']);
        timezoneTestInvoice($belgrade, $belgradeClient, 'TZ-BG-001', $belgrade->businessToday()->copy()->subDay()->toDateString());
        timezoneTestInvoice($other, $otherClient, 'TZ-AK-001', $other->businessToday()->toDateString());

        $this->actingAs($user);
        $this->getJson('/api/invoices?overdue=1')->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.invoice_number', 'TZ-BG-001')
            ->assertJsonPath('0.is_overdue', true)
            ->assertJsonPath('0.overdue_days', 1);

        $this->postJson('/api/companies/switch', ['company_id' => $other->id])->assertOk();
        auth()->setUser($user->fresh());
        $this->getJson('/api/invoices?overdue=1')->assertOk()->assertJsonCount(0);
    } finally {
        Carbon::setTestNow();
    }
});

it('does not alter persisted business dates when the company timezone changes', function () {
    [, $company, $client] = timezoneTestContext('timezone-date-preservation@example.test', 'Europe/Belgrade');
    $invoice = timezoneTestInvoice($company, $client, 'TZ-DATE-001', '2026-10-12');
    $company->update(['timezone' => 'Pacific/Auckland']);

    expect($invoice->fresh()->issue_date->toDateString())->toBe('2026-10-01')
        ->and($invoice->fresh()->due_date->toDateString())->toBe('2026-10-12');
});
