<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function createUserWithCompanyAndClient( string $email, string $currency = 'RSD' ): array {
    $user = User::create( [
        'first_name' => 'Test',
        'last_name'  => 'User',
        'email'      => $email,
        'username'   => str_replace( '@', '_', $email ),
        'password'   => 'password',
    ] );

    $company = Company::create( [
        'name'                 => 'Test Company',
        'currency'             => $currency,
        'vat_enabled'          => false,
        'invoice_start_number' => 1,
        'payment_due_days'     => 15,
        'user_id'              => $user->id,
    ] );

    $user->update( [ 'active_company_id' => $company->id ] );

    $client = $company->clients()->create( [
        'name'         => 'ACME',
        'country'      => 'Srbija',
        'client_type'  => 'b2b',
    ] );

    return [ $user, $company, $client ];
}

it( 'stores rsd snapshot fields for rsd invoice', function () {
    [ $user, $company, $client ] = createUserWithCompanyAndClient( 'rsd@example.com', 'RSD' );
    $this->actingAs( $user );

    $response = $this->postJson( '/api/invoices', [
        'client_id'      => $client->id,
        'invoice_number' => '2026-03-001',
        'issue_date'     => now()->toDateString(),
        'due_date'       => now()->addDays( 15 )->toDateString(),
        'currency'       => 'RSD',
        'payment_method' => 'bank_transfer',
        'items'          => [
            [
                'name'        => 'Usluga',
                'description' => 'Opis',
                'quantity'    => 2,
                'price'       => 100,
            ],
        ],
    ] );

    $response->assertCreated();
    $invoiceId = $response->json( 'id' );

    $this->assertDatabaseHas( 'invoices', [
        'id'             => $invoiceId,
        'company_id'     => $company->id,
        'currency'       => 'RSD',
        'fx_rate_to_rsd' => 1.000000,
        'total_original' => 200.00,
        'total_rsd'      => 200.00,
    ] );
} );

it( 'stores foreign currency snapshot fields for non-rsd invoice', function () {
    Http::fake( [
        'https://kurs.resenje.org/*' => Http::response( [
            'currency'        => 'EUR',
            'exchange_middle' => 117.25,
            'date'            => '2026-03-31',
        ], 200 ),
    ] );

    [ $user, $company, $client ] = createUserWithCompanyAndClient( 'eur@example.com', 'EUR' );
    $this->actingAs( $user );

    $response = $this->postJson( '/api/invoices', [
        'client_id'      => $client->id,
        'invoice_number' => '2026-03-002',
        'issue_date'     => now()->toDateString(),
        'due_date'       => now()->addDays( 15 )->toDateString(),
        'currency'       => 'EUR',
        'payment_method' => 'bank_transfer',
        'items'          => [
            [
                'name'        => 'Usluga',
                'description' => 'Opis',
                'quantity'    => 1,
                'price'       => 100,
            ],
        ],
    ] );

    $response->assertCreated();
    $invoiceId = $response->json( 'id' );

    $this->assertDatabaseHas( 'invoices', [
        'id'             => $invoiceId,
        'company_id'     => $company->id,
        'currency'       => 'EUR',
        'fx_rate_to_rsd' => 117.250000,
        'total_original' => 100.00,
        'total_rsd'      => 11725.00,
    ] );
} );

it( 'prevents reading invoice from another tenant', function () {
    [ $owner, $ownerCompany, $ownerClient ] = createUserWithCompanyAndClient( 'owner@example.com', 'RSD' );
    $this->actingAs( $owner );

    $invoiceResponse = $this->postJson( '/api/invoices', [
        'client_id'      => $ownerClient->id,
        'invoice_number' => '2026-03-003',
        'issue_date'     => now()->toDateString(),
        'due_date'       => now()->addDays( 15 )->toDateString(),
        'currency'       => 'RSD',
        'payment_method' => 'bank_transfer',
        'items'          => [
            [ 'name' => 'X', 'description' => 'X', 'quantity' => 1, 'price' => 100 ],
        ],
    ] )->assertCreated();

    $invoiceId = $invoiceResponse->json( 'id' );

    [ $otherUser ] = createUserWithCompanyAndClient( 'other@example.com', 'RSD' );
    $this->actingAs( $otherUser );

    $this->getJson( "/api/invoices/{$invoiceId}" )->assertForbidden();
} );
