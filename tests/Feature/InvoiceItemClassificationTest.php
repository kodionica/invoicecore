<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;

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

it('updates only item classifications and preserves financial fields', function () {
    [$user, $company, $client] = classificationContext('classification-update@example.com');
    $this->actingAs($user);

    $invoice = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'items' => [[
            'name' => 'Stavka',
            'description' => 'Stavka',
            'quantity' => 2,
            'price' => 125,
            'income_category' => 'unclassified',
        ]],
    ])->assertCreated()->json();

    $item = $company->invoices()->findOrFail($invoice['id'])->items()->firstOrFail();
    $before = $item->only(['quantity', 'price', 'sub_total', 'tax_amount', 'total', 'description']);

    $this->patchJson("/api/invoices/{$invoice['id']}/items/classifications", [
        'items' => [['id' => $item->id, 'income_category' => 'product']],
    ])->assertOk()
        ->assertJsonPath('items.0.income_category', 'product');

    expect($item->refresh()->only(['quantity', 'price', 'sub_total', 'tax_amount', 'total', 'description']))
        ->toBe($before);
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
        'items' => [['id' => $second['items'][0]['id'], 'income_category' => 'product']],
    ])->assertUnprocessable();

    expect($company->invoices()->findOrFail($second['id'])->items()->firstOrFail()->income_category)
        ->toBeNull();
});
