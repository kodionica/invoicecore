<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

function invoiceListContext(string $email): array
{
    $user = User::create(['first_name' => 'List', 'last_name' => 'Tester', 'email' => $email, 'username' => str_replace('@', '_', $email), 'password' => 'password']);
    $company = $user->companies()->create(['name' => 'List Company', 'tax_id' => 'LIST'.random_int(10000000, 99999999), 'registration_number' => 'LIST'.random_int(100000, 999999), 'currency' => 'RSD', 'vat_enabled' => false]);
    $client = $company->clients()->create(['name' => 'List Client', 'client_type' => 'b2b']);
    $user->update(['active_company_id' => $company->id]);
    return [$user, $company, $client];
}

function listInvoice(Company $company, Client $client, string $number, string $status, string $currency, string $date): Invoice
{
    return Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => $number, 'issue_date' => $date, 'due_date' => $date, 'currency' => $currency, 'status' => $status, 'total' => 100, 'subtotal_original' => 100, 'tax_original' => 0, 'total_original' => 100, 'subtotal_rsd' => 100, 'tax_rsd' => 0, 'total_rsd' => 100, 'fx_rate_to_rsd' => 1, 'fx_provider' => 'test', 'fx_date' => $date, 'include_in_kpo' => false, 'vat_enabled_at_issue' => false]);
}

it('combines invoice filters before pagination and keeps legacy list responses compatible', function () {
    [$user, $company, $client] = invoiceListContext('invoice-list-filters@example.test');
    listInvoice($company, $client, 'FILTER-EUR-1', 'sent', 'EUR', '2026-03-01');
    listInvoice($company, $client, 'FILTER-RSD-2', 'paid', 'RSD', '2026-04-01');
    listInvoice($company, $client, 'OTHER-3', 'sent', 'EUR', '2026-05-01');

    $this->actingAs($user);
    $response = $this->getJson('/api/invoices?page=1&per_page=1&search=FILTER&status=sent&currency=EUR&issue_date_from=2026-01-01&issue_date_to=2026-03-31')->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.invoice_number'))->toBe('FILTER-EUR-1')
        ->and($response->json('last_page'))->toBe(1);
});

it('uses existing scoped status endpoints for successful and failed bulk-style operations', function () {
    [$user, $company, $client] = invoiceListContext('invoice-list-bulk@example.test');
    $first = listInvoice($company, $client, 'BULK-1', 'sent', 'RSD', '2026-03-01');
    $second = listInvoice($company, $client, 'BULK-2', 'sent', 'RSD', '2026-03-01');
    $otherUser = User::create(['first_name' => 'Other', 'last_name' => 'List', 'email' => 'other-list@example.test', 'username' => 'other_list', 'password' => 'password']);
    $otherCompany = $otherUser->companies()->create(['name' => 'Other List', 'tax_id' => 'OTHERLIST'.random_int(10000000, 99999999), 'registration_number' => 'OTHERLIST'.random_int(100000, 999999), 'currency' => 'RSD']);
    $otherClient = $otherCompany->clients()->create(['name' => 'Other Client', 'client_type' => 'b2b']);
    $foreignInvoice = listInvoice($otherCompany, $otherClient, 'BULK-FOREIGN', 'sent', 'RSD', '2026-03-01');

    $this->actingAs($user);
    $this->putJson("/api/invoices/{$first->id}", ['status' => 'paid'])->assertUnprocessable()->assertJsonValidationErrors('paid_at');
    $this->putJson("/api/invoices/{$first->id}", ['status' => 'paid', 'paid_at' => '2026-03-10', 'payment_note' => 'Grupna uplata'])->assertOk();
    $this->putJson("/api/invoices/{$second->id}", ['status' => 'draft'])->assertConflict();
    $this->putJson("/api/invoices/{$foreignInvoice->id}", ['status' => 'paid', 'paid_at' => '2026-03-10'])->assertForbidden();

    expect($first->fresh()->status)->toBe('paid')->and($first->fresh()->paid_at?->toDateString())->toBe('2026-03-10')->and($second->fresh()->status)->toBe('sent');
});
