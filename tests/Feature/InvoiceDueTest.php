<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

function dueInvoiceFixture(string $email): array
{
    $user = User::create([
        'first_name' => 'Due',
        'last_name' => 'Test',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = Company::create([
        'name' => 'Due Company',
        'user_id' => $user->id,
        'vat_enabled' => false,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'Due Client']);

    return [$user, $company, $client];
}

function dueInvoice(Company $company, int $clientId, string $number, ?string $dueDate, string $status = 'sent'): Invoice
{
    return Invoice::create([
        'company_id' => $company->id,
        'client_id' => $clientId,
        'invoice_number' => $number,
        'issue_date' => now()->subDays(10)->toDateString(),
        'due_date' => $dueDate,
        'currency' => 'RSD',
        'fx_rate_to_rsd' => 1,
        'total' => 100,
        'total_original' => 100,
        'total_rsd' => 100,
        'status' => $status,
        'include_in_kpo' => true,
        'vat_enabled_at_issue' => false,
    ]);
}

it('marks only eligible past-due invoices as overdue', function () {
    [$user, $company, $client] = dueInvoiceFixture('due-rules@example.test');
    $past = dueInvoice($company, $client->id, 'DUE-PAST', now()->subDay()->toDateString());
    $today = dueInvoice($company, $client->id, 'DUE-TODAY', now()->toDateString());
    $future = dueInvoice($company, $client->id, 'DUE-FUTURE', now()->addDay()->toDateString());
    $paid = dueInvoice($company, $client->id, 'DUE-PAID', now()->subDay()->toDateString(), 'paid');
    $draft = dueInvoice($company, $client->id, 'DUE-DRAFT', now()->subDay()->toDateString(), 'draft');
    $cancelled = dueInvoice($company, $client->id, 'DUE-CANCELLED', now()->subDay()->toDateString(), 'cancelled');
    $withoutDueDate = new Invoice(['status' => 'sent']);
    $withoutDueDate->due_date = null;
    $this->actingAs($user);

    $response = $this->getJson('/api/invoices')->assertOk();
    $rows = collect($response->json());

    expect($rows->firstWhere('id', $past->id)['is_overdue'])->toBeTrue()
        ->and($rows->firstWhere('id', $past->id)['overdue_days'])->toBe(1)
        ->and($rows->firstWhere('id', $today->id)['is_overdue'])->toBeFalse()
        ->and($rows->firstWhere('id', $future->id)['is_overdue'])->toBeFalse()
        ->and($rows->firstWhere('id', $paid->id)['is_overdue'])->toBeFalse()
        ->and($rows->firstWhere('id', $draft->id)['is_overdue'])->toBeFalse()
        ->and($rows->firstWhere('id', $cancelled->id)['is_overdue'])->toBeFalse()
        ->and($withoutDueDate->isOverdue())->toBeFalse();

    $this->getJson('/api/invoices/'.$past->id)
        ->assertOk()
        ->assertJsonPath('invoice.is_overdue', true)
        ->assertJsonPath('invoice.overdue_days', 1);
});

it('filters overdue invoices on the backend before returning the list', function () {
    [$user, $company, $client] = dueInvoiceFixture('due-filter@example.test');
    $past = dueInvoice($company, $client->id, 'DUE-FILTER-PAST', now()->subDays(2)->toDateString());
    $today = dueInvoice($company, $client->id, 'DUE-FILTER-TODAY', now()->toDateString());
    $this->actingAs($user);

    $this->getJson('/api/invoices?overdue=1')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $past->id)
        ->assertJsonPath('0.is_overdue', true);
});

it('returns a ten-day overdue unpaid invoice and excludes paid and future invoices', function () {
    [$user, $company, $client] = dueInvoiceFixture('due-ten-days@example.test');
    $overdue = dueInvoice($company, $client->id, 'DUE-TEN-DAYS', now()->subDays(10)->toDateString());
    $paid = dueInvoice($company, $client->id, 'DUE-TEN-DAYS-PAID', now()->subDays(10)->toDateString(), 'paid');
    $paid->update(['paid_at' => now()->subDays(2)->toDateString()]);
    dueInvoice($company, $client->id, 'DUE-TEN-DAYS-FUTURE', now()->addDays(10)->toDateString());
    $this->actingAs($user);

    $this->getJson('/api/invoices?overdue=1')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $overdue->id)
        ->assertJsonPath('0.is_overdue', true)
        ->assertJsonPath('0.overdue_days', 10);
});

it('keeps overdue filtering isolated to the active company', function () {
    [$user, $company, $client] = dueInvoiceFixture('due-owner@example.test');
    dueInvoice($company, $client->id, 'DUE-OWNER', now()->subDay()->toDateString());
    [$otherUser, $otherCompany, $otherClient] = dueInvoiceFixture('due-other@example.test');
    $other = dueInvoice($otherCompany, $otherClient->id, 'DUE-OTHER', now()->subDay()->toDateString());
    $this->actingAs($user);

    $this->getJson('/api/invoices?overdue=1')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonMissing(['id' => $other->id]);
});
