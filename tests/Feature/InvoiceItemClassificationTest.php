<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function classificationContext(string $email): array
{
    $user = User::create([
        'first_name' => 'Classification',
        'last_name' => 'Tester',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);

    $company = Company::create([
        'name' => 'Classification Company',
        'currency' => 'RSD',
        'vat_enabled' => false,
        'invoice_start_number' => 1,
        'payment_due_days' => 15,
        'user_id' => $user->id,
    ]);

    $user->update(['active_company_id' => $company->id]);

    $client = $company->clients()->create([
        'name' => 'Classification Client',
        'country' => 'Srbija',
        'client_type' => 'b2b',
    ]);

    return [$user, $company, $client];
}

it('stores item income categories and accepts all supported values', function () {
    [$user, $company, $client] = classificationContext('classification@example.com');
    $this->actingAs($user);

    $response = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'items' => [
            ['name' => 'Proizvod', 'description' => 'Proizvod', 'quantity' => 1, 'price' => 100, 'income_category' => 'product'],
            ['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 200, 'income_category' => 'service'],
            ['name' => 'Nerazvrstano', 'description' => 'Nerazvrstano', 'quantity' => 1, 'price' => 300, 'income_category' => 'unclassified'],
        ],
    ]);

    $response->assertCreated();

    $invoiceId = $response->json('id');
    expect($response->json('items.0.income_category'))->toBe('product')
        ->and($response->json('items.1.income_category'))->toBe('service')
        ->and($response->json('items.2.income_category'))->toBe('unclassified');

    expect($company->invoices()->findOrFail($invoiceId)->items()->pluck('income_category')->all())
        ->toBe(['product', 'service', 'unclassified']);
});

it('rejects unsupported item income categories', function () {
    [$user, $company, $client] = classificationContext('invalid-classification@example.com');
    $this->actingAs($user);

    $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'items' => [[
            'name' => 'Nepoznato',
            'description' => 'Nepoznato',
            'quantity' => 1,
            'price' => 100,
            'income_category' => 'other',
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items.0.income_category');

    expect($company->invoices()->count())->toBe(0);
});

it('rejects missing, empty, non-array, malformed and duplicate classification payloads', function () {
    [$user, $company, $client] = classificationContext('classification-payload@example.com');
    $this->actingAs($user);

    $invoice = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'items' => [
            ['name' => 'Prva', 'description' => 'Prva', 'quantity' => 1, 'price' => 100],
            ['name' => 'Druga', 'description' => 'Druga', 'quantity' => 1, 'price' => 200],
        ],
    ])->assertCreated()->json();

    $firstId = $invoice['items'][0]['id'];
    $payloads = [
        [],
        ['items' => []],
        ['items' => 'not-an-array'],
        ['items' => [['id' => $firstId]]],
        ['items' => [
            ['id' => $firstId, 'income_category' => 'product'],
            ['id' => $firstId, 'income_category' => 'service'],
        ]],
    ];

    foreach ($payloads as $payload) {
        $this->patchJson("/api/invoices/{$invoice['id']}/items/classifications", $payload)
            ->assertUnprocessable();
    }

    expect($company->invoices()->findOrFail($invoice['id'])->items()->pluck('income_category')->all())
        ->toBe([null, null]);
});

it('updates only item classifications and preserves financial fields', function () {
    [$user, $company, $client] = classificationContext('classification-update@example.com');
    $this->actingAs($user);
    Http::fake([
        'https://kurs.resenje.org/*' => Http::response([
            'currency' => 'EUR',
            'exchange_middle' => 117.25,
            'date' => '2026-10-16',
        ], 200),
    ]);

    $company->update([
        'currency' => 'EUR',
        'vat_enabled' => true,
        'default_tax_percent' => 20,
    ]);

    $invoice = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => '2026-10-016',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'EUR',
        'payment_method' => 'bank_transfer',
        'note' => 'Classification regression snapshot',
        'include_in_kpo' => true,
        'items' => [[
            'name' => 'Stavka',
            'description' => 'Stavka',
            'quantity' => 2,
            'price' => 125,
            'income_category' => 'unclassified',
        ]],
    ])->assertCreated()->json();

    $item = $company->invoices()->findOrFail($invoice['id'])->items()->firstOrFail();
    $invoiceBefore = $company->invoices()->findOrFail($invoice['id'])->getAttributes();
    $itemBefore = $item->getAttributes();
    unset($itemBefore['income_category']);

    $this->patchJson("/api/invoices/{$invoice['id']}/items/classifications", [
        'items' => [['id' => $item->id, 'income_category' => 'product']],
    ])->assertOk()
        ->assertJsonPath('items.0.income_category', 'product');

    $invoiceAfter = $company->invoices()->findOrFail($invoice['id'])->getAttributes();
    $itemAfterModel = $item->refresh();
    $itemAfter = $itemAfterModel->getAttributes();
    unset($itemAfter['income_category']);

    expect($invoiceAfter)->toBe($invoiceBefore)
        ->and($itemAfter)->toBe($itemBefore)
        ->and($itemAfterModel->income_category)->toBe('product');

    $freshInvoice = collect($this->getJson('/api/invoices')->assertOk()->json())
        ->firstWhere('id', $invoice['id']);
    expect($freshInvoice['items'][0]['income_category'])->toBe('product');
});

it('preserves legacy null classification until the user saves a new value', function () {
    [$user, $company, $client] = classificationContext('classification-legacy@example.com');
    $this->actingAs($user);

    $invoice = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'items' => [[
            'name' => 'Legacy stavka',
            'description' => 'Legacy stavka',
            'quantity' => 1,
            'price' => 100,
        ]],
    ])->assertCreated()->json();

    $item = $company->invoices()->findOrFail($invoice['id'])->items()->firstOrFail();
    expect($item->income_category)->toBeNull();

    $this->patchJson("/api/invoices/{$invoice['id']}/items/classifications", [
        'items' => [['id' => $item->id, 'income_category' => 'service']],
    ])->assertOk();

    expect($item->refresh()->income_category)->toBe('service');
});

it('rejects item classification updates from another company', function () {
    [$owner, $company, $client] = classificationContext('classification-owner@example.com');
    $this->actingAs($owner);

    $invoice = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'items' => [[
            'name' => 'Tuđa stavka',
            'description' => 'Tuđa stavka',
            'quantity' => 1,
            'price' => 100,
        ]],
    ])->assertCreated()->json();

    [$otherUser] = classificationContext('classification-other@example.com');
    $this->actingAs($otherUser);

    $this->patchJson("/api/invoices/{$invoice['id']}/items/classifications", [
        'items' => [['id' => $invoice['items'][0]['id'], 'income_category' => 'product']],
    ])->assertForbidden();

    expect($company->invoices()->findOrFail($invoice['id'])->items()->firstOrFail()->income_category)
        ->toBeNull();
});

it('rejects an item that does not belong to the selected invoice', function () {
    [$user, $company, $client] = classificationContext('classification-invoice-scope@example.com');
    $this->actingAs($user);

    $first = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'items' => [['name' => 'Prva', 'description' => 'Prva', 'quantity' => 1, 'price' => 100]],
    ])->assertCreated()->json();
    $second = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'items' => [['name' => 'Druga', 'description' => 'Druga', 'quantity' => 1, 'price' => 100]],
    ])->assertCreated()->json();

    $this->patchJson("/api/invoices/{$first['id']}/items/classifications", [
        'items' => [
            ['id' => $first['items'][0]['id'], 'income_category' => 'product'],
            ['id' => $second['items'][0]['id'], 'income_category' => 'service'],
        ],
    ])->assertUnprocessable();

    expect($company->invoices()->findOrFail($first['id'])->items()->firstOrFail()->income_category)
        ->toBeNull()
        ->and($company->invoices()->findOrFail($second['id'])->items()->firstOrFail()->income_category)
        ->toBeNull();
});
