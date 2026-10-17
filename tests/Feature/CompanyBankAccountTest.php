<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Invoice;
use App\Models\User;

function bankAccountFixture(string $suffix): array
{
    $user = User::factory()->create([
        'email' => "bank-{$suffix}@example.test",
        'username' => "bank-{$suffix}",
    ]);
    $company = Company::create([
        'name' => "Bank {$suffix}",
        'tax_id' => "PIB-{$suffix}",
        'registration_number' => "MB-{$suffix}",
        'currency' => 'RSD',
        'vat_enabled' => false,
        'user_id' => $user->id,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => "Client {$suffix}"]);

    return [$user, $company, $client];
}

function bankAccountPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'Glavni RSD',
        'bank_name' => 'Test banka',
        'account_number' => '160-0000000000000-00',
        'iban' => 'RS35160000000000000000',
        'swift_bic' => 'TESTRSBG',
        'currency' => 'RSD',
        'account_type' => 'domestic',
        'payment_instructions' => 'Poziv na broj: broj fakture',
        'is_default' => true,
        'is_active' => true,
    ], $overrides);
}

it('supports multiple same-currency accounts and keeps one active default per currency', function () {
    [$user, $company] = bankAccountFixture('multiple');
    $this->actingAs($user);

    $first = $this->postJson("/api/companies/{$company->id}/bank-accounts", bankAccountPayload())->assertCreated();
    $second = $this->postJson("/api/companies/{$company->id}/bank-accounts", bankAccountPayload([
        'label' => 'Drugi RSD',
        'swift_bic' => 'OTHERBIC',
        'is_default' => false,
    ]))->assertCreated();

    expect($first->json('id'))->not->toBe($second->json('id'))
        ->and(CompanyBankAccount::where('company_id', $company->id)->where('currency', 'RSD')->where('is_default', true)->count())->toBe(1);
    $this->assertDatabaseHas('company_bank_accounts', ['account_number' => '160-0000000000000-00', 'swift_bic' => 'OTHERBIC']);
});

it('copies an account as a new non-default record without changing the original', function () {
    [$user, $company] = bankAccountFixture('copy');
    $this->actingAs($user);
    $original = $this->postJson("/api/companies/{$company->id}/bank-accounts", bankAccountPayload())->assertCreated()->json();

    $copy = $this->postJson("/api/companies/{$company->id}/bank-accounts", bankAccountPayload([
        'label' => 'Kopija',
        'swift_bic' => 'COPIEDBIC',
        'is_default' => false,
    ]))->assertCreated()->json();

    expect($copy['id'])->not->toBe($original['id'])
        ->and($copy['account_number'])->toBe($original['account_number'])
        ->and($copy['swift_bic'])->toBe('COPIEDBIC')
        ->and($copy['is_default'])->toBeFalse();
    $this->assertDatabaseHas('company_bank_accounts', ['id' => $original['id'], 'swift_bic' => 'TESTRSBG', 'is_default' => true]);
});

it('selects the currency account and stores an immutable invoice snapshot', function () {
    [$user, $company, $client] = bankAccountFixture('snapshot');
    $this->actingAs($user);
    $account = $this->postJson("/api/companies/{$company->id}/bank-accounts", bankAccountPayload())->assertCreated()->json();

    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'BANK-SNAPSHOT-1',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'bank_account_id' => $account['id'],
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();

    $invoice = Invoice::findOrFail($created->json('id'));
    expect($invoice->bank_account_id)->toBe($account['id'])
        ->and($invoice->bank_account_number)->toBe($account['account_number'])
        ->and($invoice->bank_account_swift_bic)->toBe($account['swift_bic']);

    $this->putJson("/api/companies/{$company->id}/bank-accounts/{$account['id']}", bankAccountPayload([
        'swift_bic' => 'CHANGEDBIC',
    ]))->assertOk();
    expect($invoice->fresh()->bank_account_swift_bic)->toBe('TESTRSBG');
});

it('automatically selects the active default account for the invoice currency', function () {
    [$user, $company, $client] = bankAccountFixture('automatic');
    $this->actingAs($user);
    $account = $this->postJson("/api/companies/{$company->id}/bank-accounts", bankAccountPayload([
        'currency' => 'EUR',
        'account_type' => 'foreign',
        'account_number' => 'EUR-001',
    ]))->assertCreated()->json();

    $invoice = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'BANK-AUTO-1',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'EUR',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated()->json();

    expect($invoice['bank_account_id'])->toBe($account['id'])
        ->and($invoice['bank_account_currency'])->toBe('EUR');
});

it('rejects selecting another company account', function () {
    [$user, $company, $client] = bankAccountFixture('owner');
    [$otherUser, $otherCompany] = bankAccountFixture('other');
    $this->actingAs($otherUser);
    $account = $this->postJson("/api/companies/{$otherCompany->id}/bank-accounts", bankAccountPayload(['account_number' => '999']))->assertCreated()->json();

    $this->actingAs($user)->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'BANK-CROSS-1',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'bank_account_id' => $account['id'],
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertUnprocessable();
});
