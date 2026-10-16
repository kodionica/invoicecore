<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Services\KpoAmountCalculationService;
use App\Models\User;

function amountCalculationInvoice(string $email, string $currency = 'RSD', float $rate = 1.0, float $totalRsd = 0): Invoice
{
    $user = User::create([
        'first_name' => 'KPO',
        'last_name' => 'Amounts',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = Company::create([
        'name' => 'Amount Company',
        'currency' => $currency,
        'vat_enabled' => true,
        'default_tax_percent' => 20,
        'invoice_start_number' => 1,
        'payment_due_days' => 15,
        'user_id' => $user->id,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create([
        'name' => 'Amount Client',
        'country' => 'Srbija',
        'client_type' => 'b2b',
    ]);

    return Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'AMOUNT-'.str_replace(['@', '.'], '-', $email),
        'issue_date' => '2026-10-16',
        'due_date' => '2026-10-31',
        'currency' => $currency,
        'fx_rate_to_rsd' => $rate,
        'total' => $totalRsd,
        'subtotal_original' => $totalRsd,
        'tax_original' => 0,
        'total_original' => $totalRsd,
        'subtotal_rsd' => $totalRsd,
        'tax_rsd' => 0,
        'total_rsd' => $totalRsd,
        'status' => 'draft',
        'include_in_kpo' => true,
    ]);
}

function addAmountItem(Invoice $invoice, float $total, ?string $category, ?float $subTotal = null, float $taxAmount = 0): void
{
    $invoice->items()->create([
        'name' => 'Stavka',
        'quantity' => 1,
        'price' => $total,
        'sub_total' => $subTotal ?? $total,
        'total' => $total,
        'tax_amount' => $taxAmount,
        'description' => 'Sačuvana stavka',
        'income_category' => $category,
    ]);
}

it('calculates product, service and unclassified persisted totals', function () {
    $invoice = amountCalculationInvoice('mixed-amounts@example.com', 'RSD', 1, 350);
    addAmountItem($invoice, 100, 'product');
    addAmountItem($invoice, 200, 'service');
    addAmountItem($invoice, 50, 'unclassified');

    $result = app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items'));

    expect($result['categories']['product']['amount_original'])->toBe('100.00')
        ->and($result['categories']['service']['amount_original'])->toBe('200.00')
        ->and($result['categories']['unclassified']['amount_original'])->toBe('50.00')
        ->and($result['is_complete'])->toBeFalse()
        ->and($result['unresolved_amount_rsd'])->toBe('50.00')
        ->and($result['calculated_total_rsd'])->toBe('350.00')
        ->and($result['reconciliation_discrepancy_rsd'])->toBe('0.00');
});

it('treats legacy null categories as unclassified without changing the item', function () {
    $invoice = amountCalculationInvoice('legacy-amounts@example.com', 'RSD', 1, 100);
    addAmountItem($invoice, 100, null);

    $result = app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items'));

    expect($result['categories']['unclassified']['amount_original'])->toBe('100.00')
        ->and($result['is_complete'])->toBeFalse()
        ->and($invoice->fresh('items')->first()->income_category)->toBeNull();
});

it('keeps an all-unclassified invoice unresolved', function () {
    $invoice = amountCalculationInvoice('all-unclassified@example.com', 'RSD', 1, 150);
    addAmountItem($invoice, 100, null);
    addAmountItem($invoice, 50, 'unclassified');

    $result = app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items'));

    expect($result['categories']['product']['amount_original'])->toBe('0.00')
        ->and($result['categories']['service']['amount_original'])->toBe('0.00')
        ->and($result['unresolved_amount_original'])->toBe('150.00')
        ->and($result['is_complete'])->toBeFalse();
});

it('uses the persisted FX rate for foreign currency and includes persisted VAT-inclusive totals', function () {
    $invoice = amountCalculationInvoice('eur-amounts@example.com', 'EUR', 117.25, 11725);
    addAmountItem($invoice, 100, 'service');

    $result = app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items'));

    expect($result['categories']['service']['amount_original'])->toBe('100.00')
        ->and($result['categories']['service']['amount_rsd'])->toBe('11725.00')
        ->and($result['categories']['service']['item_count'])->toBe(1);
});

it('uses the persisted item total for VAT-inclusive amounts', function () {
    $invoice = amountCalculationInvoice('vat-inclusive@example.com', 'RSD', 1, 120);
    addAmountItem($invoice, 120, 'service', 100, 20);

    $result = app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items'));

    expect($result['categories']['service']['amount_original'])->toBe('120.00')
        ->and($result['categories']['service']['amount_rsd'])->toBe('120.00');
});

it('reports a deterministic residual without applying it', function () {
    $invoice = amountCalculationInvoice('residual-amounts@example.com', 'EUR', 1.005, 10.02);
    addAmountItem($invoice, 5.01, 'product');
    addAmountItem($invoice, 5.01, 'service');

    $result = app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items'));

    expect($result['calculated_total_rsd'])->toBe('10.08')
        ->and($result['persisted_total_rsd'])->toBe('10.02')
        ->and($result['reconciliation_discrepancy_rsd'])->toBe('-0.06')
        ->and($result['proposed_reconciliation']['apply_automatically'])->toBeFalse();
});

it('reports discrepancies between persisted item totals and invoice snapshots', function () {
    $invoice = amountCalculationInvoice('snapshot-discrepancy@example.com', 'RSD', 1, 99);
    addAmountItem($invoice, 100, 'product');

    $result = app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items'));

    expect($result['calculated_total_rsd'])->toBe('100.00')
        ->and($result['persisted_total_rsd'])->toBe('99.00')
        ->and($result['reconciliation_discrepancy_rsd'])->toBe('-1.00');
});

it('does not modify persisted invoice or item financial fields', function () {
    $invoice = amountCalculationInvoice('preservation@example.com', 'EUR', 117.25, 11725);
    addAmountItem($invoice, 100, 'product', 80, 20);
    $beforeInvoice = $invoice->fresh()->only([
        'total', 'subtotal_original', 'tax_original', 'total_original',
        'subtotal_rsd', 'tax_rsd', 'total_rsd', 'fx_rate_to_rsd', 'currency',
    ]);
    $beforeItem = $invoice->fresh('items')->items->first()->only([
        'quantity', 'price', 'sub_total', 'total', 'tax_amount', 'income_category',
    ]);

    app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items'));

    expect($invoice->fresh()->only(array_keys($beforeInvoice)))->toBe($beforeInvoice)
        ->and($invoice->fresh('items')->items->first()->only(array_keys($beforeItem)))->toBe($beforeItem);
});

it('rejects an invoice without a persisted FX rate instead of fetching one', function () {
    $invoice = amountCalculationInvoice('missing-rate@example.com', 'EUR', 1, 100);
    $invoice->update(['fx_rate_to_rsd' => null]);
    addAmountItem($invoice, 100, 'service');

    expect(fn () => app(KpoAmountCalculationService::class)->calculate($invoice->fresh('items')))
        ->toThrow(InvalidArgumentException::class);
});
