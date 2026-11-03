<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function clientProfileCompany(string $email): array
{
    $user = User::factory()->create(['email' => $email, 'username' => str_replace('@', '_', $email)]);
    $company = $user->companies()->create(['name' => 'Client profile test', 'currency' => 'RSD']);
    $user->update(['active_company_id' => $company->id]);

    return [$user, $company];
}

it('supports the three types and optional identifiers and email', function () {
    [$user] = clientProfileCompany('client-profile-types@example.test');
    $this->actingAs($user)->postJson('/api/clients', ['name' => 'Domaći'])
        ->assertCreated()->assertJsonPath('country_code', 'RS')->assertJsonPath('client_type', 'company');
    $client = $this->postJson('/api/clients', [
        'name' => 'Preduzetnik', 'client_type' => 'entrepreneur', 'country_code' => 'rs',
        'tax_id' => '100001749', 'registration_number' => '00012345', 'email' => 'profile@example.test',
    ])->assertCreated()->assertJsonPath('registration_number', '00012345')->json();
    $this->putJson('/api/clients/'.$client['id'], ['email' => '', 'tax_id' => null])
        ->assertOk()->assertJsonPath('email', null)->assertJsonPath('tax_id', null);
    $this->postJson('/api/clients', ['name' => 'Fizičko lice', 'client_type' => 'individual'])
        ->assertCreated()->assertJsonPath('client_type', 'individual');
});

it('validates domestic identifiers and scopes uniqueness by tenant and country', function () {
    [$user, $company] = clientProfileCompany('client-profile-owner@example.test');
    [$other] = clientProfileCompany('client-profile-other@example.test');
    $this->actingAs($user)->postJson('/api/clients', [
        'name' => 'Invalid', 'tax_id' => '100001741', 'registration_number' => '123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['tax_id', 'registration_number']);
    $data = ['name' => 'Foreign', 'country_code' => 'de', 'tax_id' => '001-A', 'preferred_language' => 'sr-Latn'];
    $client = $this->postJson('/api/clients', $data)->assertCreated()->assertJsonPath('tax_id', '001-A')->json();
    $this->postJson('/api/clients', $data)->assertUnprocessable()->assertJsonValidationErrors('tax_id');
    $this->postJson('/api/clients', array_replace($data, ['country_code' => 'FR']))->assertCreated();
    $this->postJson('/api/clients', array_replace($data, ['preferred_language' => 'en']))->assertUnprocessable();
    $this->actingAs($other)->postJson('/api/clients', $data)->assertCreated();
    $this->putJson('/api/clients/'.$client['id'], ['name' => 'Unauthorized'])->assertForbidden();

    $legacy = $company->clients()->create(['name' => 'Legacy', 'country_code' => 'RS', 'tax_id' => 'old']);
    $this->actingAs($user)->putJson('/api/clients/'.$legacy->id, ['name' => 'Updated', 'tax_id' => 'old'])
        ->assertOk()->assertJsonPath('tax_id', 'old');
    $unknown = $company->clients()->create(['name' => 'Unknown', 'country' => 'Ostale', 'email' => 'old-value']);
    $this->putJson('/api/clients/'.$unknown->id, ['name' => 'Updated', 'country_code' => null, 'email' => 'old-value'])
        ->assertOk()->assertJsonPath('country_code', null);

});

it('uses client defaults when creating an invoice and lets an explicit currency override them', function () {
    [$user, $company] = clientProfileCompany('client-profile-invoice@example.test');
    $buyer = $company->clients()->create([
        'name' => 'Client', 'country_code' => 'DE', 'default_currency' => 'EUR', 'preferred_language' => 'sr-Latn',
    ]);
    $this->mock(\App\Services\ExchangeRateService::class, function ($mock): void {
        $mock->shouldReceive('getTodayRate')->with('EUR')->once()->andReturn(['exchange_middle' => 117, 'provider' => 'test']);
        $mock->shouldReceive('getTodayRate')->with('RSD')->once()->andReturn(['exchange_middle' => 1, 'provider' => 'internal']);
    });
    $payload = ['client_id' => $buyer->id, 'items' => [['name' => 'Usluga', 'quantity' => 1, 'price' => 100]]];
    $first = $this->actingAs($user)->postJson('/api/invoices', $payload)->assertCreated()
        ->assertJsonPath('currency', 'EUR')->json('id');
    expect(Invoice::findOrFail($first)->document_settings['locale'])->toBe('sr-Latn');
    $this->postJson('/api/invoices', $payload + ['currency' => 'RSD', 'document_locale' => 'sr-Latn'])
        ->assertCreated()->assertJsonPath('currency', 'RSD');
    $this->postJson('/api/invoices', $payload + ['document_locale' => 'en'])->assertUnprocessable();
    $buyer->update(['default_currency' => 'RSD']);
    expect(Invoice::findOrFail($first)->currency)->toBe('EUR');
});

it('backfills legacy countries and types without touching referenced invoices', function () {
    $original = DB::getDefaultConnection();
    config(['database.connections.client_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('client_migration_test');
    try {
        DB::statement('CREATE TABLE companies (id INTEGER PRIMARY KEY)');
        DB::table('companies')->insert(['id' => 1]);
        (require database_path('migrations/2026_02_04_211054_create_clients_table.php'))->up();
        (require database_path('migrations/2026_03_30_154716_add_client_type_to_clients_table.php'))->up();
        (require database_path('migrations/2026_02_04_211101_create_invoices_table.php'))->up();
        DB::table('clients')->insert([
            ['id' => 5, 'company_id' => 1, 'name' => 'Known', 'country' => 'Srbija', 'client_type' => 'b2b'],
            ['id' => 7, 'company_id' => 1, 'name' => 'Unknown', 'country' => 'Ostale', 'client_type' => 'b2c'],
        ]);
        DB::table('invoices')->insert([
            'id' => 9, 'company_id' => 1, 'client_id' => 5, 'invoice_number' => 'OLD',
            'issue_date' => '2026-01-01', 'due_date' => '2026-01-03', 'pdf_path' => 'historical.pdf',
        ]);
        DB::table('clients')->where('id', 5)->update(['tax_id' => '100001749']);
        DB::table('clients')->insert([
            'id' => 8, 'company_id' => 1, 'name' => 'Collision', 'country' => 'RS',
            'client_type' => 'b2b', 'tax_id' => '100001749',
        ]);
        $migration = require database_path('migrations/2026_11_03_000003_expand_client_profiles.php');
        expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Client identifier conflict');
        expect(\Illuminate\Support\Facades\Schema::hasColumn('clients', 'country_code'))->toBeFalse();
        DB::table('clients')->where('id', 8)->delete();

        $migration->up();
        expect((array) DB::table('clients')->find(5))->toMatchArray(['country' => 'Srbija', 'country_code' => 'RS', 'client_type' => 'company']);
        expect((array) DB::table('clients')->find(7))->toMatchArray(['country' => 'Ostale', 'country_code' => null, 'client_type' => 'individual']);
        expect(DB::table('invoices')->find(9)->pdf_path)->toBe('historical.pdf');
    } finally {
        DB::setDefaultConnection($original);
        DB::purge('client_migration_test');
    }
});
