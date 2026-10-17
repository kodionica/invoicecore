<?php

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Invoice;
use App\Models\User;

it('preserves the saved currency and bank account when editing unrelated invoice data', function (string $currency) {
    $user = User::create([
        'first_name' => 'Currency',
        'last_name' => 'Test',
        'email' => 'invoice-edit-'.$currency.'@example.test',
        'username' => 'invoice-edit-'.$currency,
        'password' => 'password',
    ]);
    $company = Company::create([
        'name' => 'Currency Company '.$currency,
        'user_id' => $user->id,
        'vat_enabled' => false,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'Currency Client']);
    $account = CompanyBankAccount::create([
        'company_id' => $company->id,
        'label' => 'Sačuvani račun',
        'bank_name' => 'Test banka',
        'account_number' => $currency.'-001',
        'currency' => $currency,
        'account_type' => $currency === 'RSD' ? 'domestic' : 'foreign',
        'is_default' => true,
        'is_active' => true,
    ]);
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'EDIT-'.$currency,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => $currency,
        'fx_rate_to_rsd' => 1,
        'subtotal_original' => 100,
        'tax_original' => 0,
        'total_original' => 100,
        'subtotal_rsd' => 100,
        'tax_rsd' => 0,
        'total_rsd' => 100,
        'total' => 100,
        'status' => 'draft',
        'include_in_kpo' => false,
        'vat_enabled_at_issue' => false,
        'bank_account_id' => $account->id,
        'bank_account_bank_name' => $account->bank_name,
        'bank_account_number' => $account->account_number,
        'bank_account_currency' => $account->currency,
    ]);
    $this->actingAs($user);

    $this->putJson('/api/invoices/'.$invoice->id, ['note' => 'Izmenjena napomena'])
        ->assertOk()
        ->assertJsonPath('currency', $currency)
        ->assertJsonPath('bank_account_id', $account->id);

    expect($invoice->fresh()->currency)->toBe($currency)
        ->and($invoice->fresh()->bank_account_id)->toBe($account->id);
})->with(['EUR', 'USD', 'RSD']);
