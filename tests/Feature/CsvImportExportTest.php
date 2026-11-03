<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function csvTestContext(string $email): array
{
    $user = User::create([
        'first_name' => 'CSV', 'last_name' => 'Tester', 'email' => $email,
        'username' => str_replace('@', '_', $email), 'password' => 'password',
    ]);
    $company = $user->companies()->create([
        'name' => 'CSV Company', 'tax_id' => 'CSV'.random_int(10000000, 99999999),
        'registration_number' => 'CSV'.random_int(100000, 999999), 'currency' => 'RSD',
        'vat_enabled' => false,
    ]);
    $user->update(['active_company_id' => $company->id]);
    return [$user, $company];
}

function csvUpload(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('invoice.csv', $content);
}

it('previews BOM semicolon CSV and imports multiple invoice items atomically', function () {
    [$user, $company] = csvTestContext('csv-invoices@example.test');
    $client = $company->clients()->create(['name' => 'CSV Client', 'email' => 'client@example.test']);
    $csv = "\xEF\xBB\xBFinvoice_number;issue_date;due_date;currency;client_id;item_name;quantity;price\nCSV-1;2026-10-01;2026-10-15;RSD;{$client->id};Prva stavka;1;100\nCSV-1;2026-10-01;2026-10-15;RSD;{$client->id};Druga stavka;2;50\n";

    $this->actingAs($user);
    $preview = $this->post('/api/import-export/invoices/preview', ['file' => csvUpload($csv)])->assertOk();
    expect($preview->json('summary.invoices'))->toBe(1)->and($preview->json('summary.items'))->toBe(2);

    $mapping = $preview->json('mapping');
    $this->withHeaders(['Accept' => 'application/json'])->post('/api/import-export/invoices/import', [
        'file' => csvUpload($csv), 'mapping' => json_encode($mapping), 'confirmed' => 1,
    ])->assertOk();

    $invoice = Invoice::where('company_id', $company->id)->where('invoice_number', 'CSV-1')->firstOrFail();
    expect($invoice->include_in_kpo)->toBeFalse()->and($invoice->items()->count())->toBe(2);
});

it('does not import a client from another company or leave partial invoice data on failure', function () {
    [$user, $company] = csvTestContext('csv-isolation@example.test');
    $otherUser = User::create(['first_name' => 'Other', 'last_name' => 'CSV', 'email' => 'other-csv@example.test', 'username' => 'other_csv', 'password' => 'password']);
    $otherCompany = $otherUser->companies()->create(['name' => 'Other CSV', 'tax_id' => 'OTHER'.random_int(10000000, 99999999), 'registration_number' => 'OTHER'.random_int(100000, 999999), 'currency' => 'RSD']);
    $otherClient = $otherCompany->clients()->create(['name' => 'Private Client']);
    $csv = "invoice_number,issue_date,due_date,currency,client_id,item_name,quantity,price\nCSV-FAIL,2026-10-01,2026-10-15,RSD,{$otherClient->id},Item,1,100\n";

    $this->actingAs($user);
    $preview = $this->post('/api/import-export/invoices/preview', ['file' => csvUpload($csv)])->assertOk();
    $this->withHeaders(['Accept' => 'application/json'])->post('/api/import-export/invoices/import', [
        'file' => csvUpload($csv), 'mapping' => json_encode($preview->json('mapping')), 'confirmed' => 1,
    ])->assertStatus(422);

    expect(Invoice::where('company_id', $company->id)->where('invoice_number', 'CSV-FAIL')->exists())->toBeFalse();
});

it('exports only the active company and protects text cells from spreadsheet formulas', function () {
    [$user, $company] = csvTestContext('csv-export@example.test');
    $company->clients()->create(['name' => '=FORMULA', 'email' => 'export@example.test']);
    $otherUser = User::create(['first_name' => 'Other', 'last_name' => 'Export', 'email' => 'other-export@example.test', 'username' => 'other_export', 'password' => 'password']);
    $otherCompany = $otherUser->companies()->create(['name' => 'Other Export', 'tax_id' => 'EXP'.random_int(10000000, 99999999), 'registration_number' => 'EXP'.random_int(100000, 999999), 'currency' => 'RSD']);
    $otherCompany->clients()->create(['name' => 'Should not export']);

    $this->actingAs($user);
    $response = $this->get('/api/import-export/clients/export')->assertOk();
    expect($response->streamedContent())->toContain("'=FORMULA")->not->toContain('Should not export');
});

it('rejects invalid invoice rows before import and imports clients without overwriting existing ones', function () {
    [$user, $company] = csvTestContext('csv-validation@example.test');
    $this->actingAs($user);

    $invalid = "invoice_number,issue_date,due_date,currency,client_name,item_name,quantity,price\nBAD,not-a-date,2026-10-15,EURO,Unknown,,0,-1\n";
    $this->post('/api/import-export/invoices/preview', ['file' => csvUpload($invalid)])
        ->assertOk()
        ->assertJsonPath('can_import', false)
        ->assertJsonStructure(['errors']);

    $clients = "name;email;address\nNew Client;new@example.test;Test 1\n";
    $preview = $this->post('/api/import-export/clients/preview', ['file' => csvUpload($clients)])->assertOk();
    $this->withHeaders(['Accept' => 'application/json'])->post('/api/import-export/clients/import', [
        'file' => csvUpload($clients), 'mapping' => json_encode($preview->json('mapping')), 'confirmed' => 1,
    ])->assertOk();
    expect($company->clients()->where('email', 'new@example.test')->exists())->toBeTrue();
});

it('maps legacy client types and shares country and identifier validation with the API', function () {
    [$user, $company] = csvTestContext('csv-client-profiles@example.test');
    $this->actingAs($user);
    $csv = "name;country;client_type;tax_id;default_currency;preferred_language\nForeign;Nemačka;b2b;001-A;EUR;sr-Latn\n";
    $preview = $this->post('/api/import-export/clients/preview', ['file' => csvUpload($csv)])
        ->assertOk()->assertJsonPath('can_import', true);
    $this->withHeaders(['Accept' => 'application/json'])->post('/api/import-export/clients/import', [
        'file' => csvUpload($csv), 'mapping' => json_encode($preview->json('mapping')), 'confirmed' => 1,
    ])->assertOk();
    $this->assertDatabaseHas('clients', [
        'company_id' => $company->id, 'name' => 'Foreign', 'country' => 'Nemačka',
        'country_code' => 'DE', 'client_type' => 'company', 'tax_id' => '001-A', 'default_currency' => 'EUR',
    ]);
    $this->post('/api/import-export/clients/preview', ['file' => csvUpload($csv)])
        ->assertOk()->assertJsonPath('can_import', false);
    expect($this->get('/api/import-export/clients/export')->streamedContent())
        ->toContain('country_code', 'preferred_language', '001-A');
});
