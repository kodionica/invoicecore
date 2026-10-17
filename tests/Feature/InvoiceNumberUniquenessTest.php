<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

function invoiceNumberContext(string $email): array
{
    $user = User::create([
        'first_name' => 'Number',
        'last_name' => 'Test',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = $user->companies()->create([
        'name' => 'Number Test Company',
        'currency' => 'RSD',
        'vat_enabled' => false,
        'invoice_start_number' => 1,
        'payment_due_days' => 15,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'Number Client']);

    return [$user, $company, $client];
}

function invoiceNumberPayload(int $clientId, string $number): array
{
    return [
        'client_id' => $clientId,
        'invoice_number' => $number,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'include_in_kpo' => false,
        'items' => [[
            'name' => 'Test stavka',
            'description' => 'Test stavka',
            'quantity' => 1,
            'price' => 100,
            'income_category' => 'unclassified',
        ]],
    ];
}

it('has a unique invoice number index scoped to the company', function () {
    $index = collect(Schema::getIndexes('invoices'))
        ->firstWhere('name', 'invoices_company_invoice_unique');

    expect($index)->not->toBeNull()
        ->and($index['unique'])->toBeTrue()
        ->and($index['columns'])->toBe(['company_id', 'invoice_number']);
});

it('rejects a duplicate invoice number through the database constraint', function () {
    [$user, $company, $client] = invoiceNumberContext('invoice-number-create@example.test');
    $this->actingAs($user);

    $this->postJson('/api/invoices', invoiceNumberPayload($client->id, 'DUPLICATE-001'))
        ->assertCreated();

    $this->postJson('/api/invoices', invoiceNumberPayload($client->id, 'DUPLICATE-001'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invoice_number')
        ->assertJsonPath('errors.invoice_number.0', 'Broj fakture već postoji u aktivnoj firmi.');

    expect(Invoice::where('company_id', $company->id)->where('invoice_number', 'DUPLICATE-001')->count())->toBe(1);
});

it('allows the same invoice number in different companies', function () {
    [$firstUser, $firstCompany, $firstClient] = invoiceNumberContext('invoice-number-company-one@example.test');
    [$secondUser, $secondCompany, $secondClient] = invoiceNumberContext('invoice-number-company-two@example.test');

    $this->actingAs($firstUser);
    $this->postJson('/api/invoices', invoiceNumberPayload($firstClient->id, 'PER-COMPANY-001'))
        ->assertCreated();

    $this->actingAs($secondUser);
    $this->postJson('/api/invoices', invoiceNumberPayload($secondClient->id, 'PER-COMPANY-001'))
        ->assertCreated();

    expect(Invoice::whereIn('company_id', [$firstCompany->id, $secondCompany->id])
        ->where('invoice_number', 'PER-COMPANY-001')
        ->count())->toBe(2);
});

it('allows an invoice to retain its number and rejects changing it to another invoice number', function () {
    [$user, $company, $client] = invoiceNumberContext('invoice-number-update@example.test');
    $this->actingAs($user);

    $first = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'UPDATE-001',
        'issue_date' => now(),
        'due_date' => now()->addDays(15),
        'currency' => 'RSD',
        'fx_rate_to_rsd' => 1,
        'total' => 0,
        'subtotal_original' => 0,
        'tax_original' => 0,
        'total_original' => 0,
        'subtotal_rsd' => 0,
        'tax_rsd' => 0,
        'total_rsd' => 0,
        'status' => 'draft',
        'include_in_kpo' => false,
        'vat_enabled_at_issue' => false,
    ]);
    $second = $first->replicate();
    $second->invoice_number = 'UPDATE-002';
    $second->save();

    $this->putJson("/api/invoices/{$first->id}", [
        'invoice_number' => ' UPDATE-001 ',
        'vat_enabled_at_issue' => false,
    ])->assertOk();

    $this->putJson("/api/invoices/{$second->id}", [
        'invoice_number' => ' UPDATE-001 ',
        'vat_enabled_at_issue' => false,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('invoice_number');

    expect($first->fresh()->invoice_number)->toBe('UPDATE-001')
        ->and($second->fresh()->invoice_number)->toBe('UPDATE-002');
});
