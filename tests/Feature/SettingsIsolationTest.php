<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function createSettingsTestUser( string $email ): User {
    return User::create( [
        'first_name' => 'Settings',
        'last_name'  => 'User',
        'email'      => $email,
        'username'   => str_replace( '@', '_', $email ),
        'password'   => 'password',
    ] );
}

function settingsIsolationMigration(): object {
    return require base_path( 'database/migrations/2026_10_15_000004_add_user_id_to_settings_table.php' );
}

it( 'returns defaults when the user has no settings record', function () {
    $user = createSettingsTestUser( 'settings-defaults@example.com' );

    $this->actingAs( $user )
        ->getJson( '/api/settings' )
        ->assertOk()
        ->assertExactJson( [
            'language'      => 'sr-Latn',
            'notifications' => [
                'invoices' => true,
                'clients'  => false,
            ],
        ] );
} );

it( 'creates and updates settings only for the authenticated user', function () {
    $user = createSettingsTestUser( 'settings-update@example.com' );

    $this->actingAs( $user )
        ->putJson( '/api/settings', [
            'language'      => 'en',
            'notifications' => [
                'invoices' => false,
                'clients'  => true,
            ],
        ] )
        ->assertOk()
        ->assertJsonStructure( [ 'message', 'settings' ] )
        ->assertJsonPath( 'settings.language', 'en' )
        ->assertJsonPath( 'settings.notifications.invoices', false )
        ->assertJsonPath( 'settings.notifications.clients', true );

    $this->actingAs( $user )
        ->putJson( '/api/settings', [
            'language' => 'de',
        ] )
        ->assertOk()
        ->assertJsonPath( 'settings.language', 'de' )
        ->assertJsonPath( 'settings.notifications.invoices', false )
        ->assertJsonPath( 'settings.notifications.clients', true );

    expect( Setting::where( 'user_id', $user->id )->where( 'key', 'app_settings' )->count() )->toBe( 1 );
} );

it( 'keeps settings isolated between users and ignores a submitted user id', function () {
    $user = createSettingsTestUser( 'settings-owner@example.com' );
    $otherUser = createSettingsTestUser( 'settings-other@example.com' );

    $this->actingAs( $user )->putJson( '/api/settings', [
        'user_id'       => $otherUser->id,
        'language'      => 'en',
        'notifications' => [ 'invoices' => false ],
    ] )->assertOk();

    $this->actingAs( $otherUser )
        ->getJson( '/api/settings' )
        ->assertOk()
        ->assertExactJson( [
            'language'      => 'sr-Latn',
            'notifications' => [
                'invoices' => true,
                'clients'  => false,
            ],
        ] );

    expect( Setting::where( 'user_id', $user->id )->where( 'key', 'app_settings' )->exists() )->toBeTrue();
    expect( Setting::where( 'user_id', $otherUser->id )->where( 'key', 'app_settings' )->exists() )->toBeFalse();
} );

it( 'returns different explicitly saved settings for each user', function () {
    $user = createSettingsTestUser( 'settings-explicit-one@example.com' );
    $otherUser = createSettingsTestUser( 'settings-explicit-two@example.com' );

    $this->actingAs( $user )->putJson( '/api/settings', [
        'language'      => 'en',
        'notifications' => [ 'invoices' => false, 'clients' => true ],
    ] )->assertOk();

    $this->actingAs( $otherUser )->putJson( '/api/settings', [
        'language'      => 'de',
        'notifications' => [ 'invoices' => true, 'clients' => true ],
    ] )->assertOk();

    $this->actingAs( $user )
        ->getJson( '/api/settings' )
        ->assertExactJson( [
            'language'      => 'en',
            'notifications' => [ 'invoices' => false, 'clients' => true ],
        ] );

    $this->actingAs( $otherUser )
        ->getJson( '/api/settings' )
        ->assertExactJson( [
            'language'      => 'de',
            'notifications' => [ 'invoices' => true, 'clients' => true ],
        ] );
} );

it( 'does not use the legacy global settings record', function () {
    $legacyValue = json_encode( [
        'language'      => 'legacy',
        'notifications' => [ 'invoices' => false, 'clients' => true ],
    ], JSON_UNESCAPED_UNICODE );

    Setting::create( [
        'key'   => 'app_settings',
        'value' => $legacyValue,
    ] );

    $user = createSettingsTestUser( 'settings-legacy@example.com' );

    $this->actingAs( $user )
        ->getJson( '/api/settings' )
        ->assertOk()
        ->assertJsonPath( 'language', 'sr-Latn' );

    expect( Setting::whereNull( 'user_id' )->where( 'key', 'app_settings' )->value( 'value' ) )->toBe( $legacyValue );
} );

it( 'deletes user settings when the user is deleted', function () {
    $user = createSettingsTestUser( 'settings-delete@example.com' );

    $this->actingAs( $user )->putJson( '/api/settings', [ 'language' => 'en' ] )->assertOk();

    $settingId = Setting::where( 'user_id', $user->id )->value( 'id' );
    $user->delete();

    $this->assertDatabaseMissing( 'settings', [ 'id' => $settingId ] );
} );

it( 'stops rollback when user-owned settings exist without changing them', function () {
    $user = createSettingsTestUser( 'settings-rollback-user@example.com' );
    $setting = $user->settings()->create( [
        'key'   => 'app_settings',
        'value' => '{"language":"en"}',
    ] );

    expect( fn() => settingsIsolationMigration()->down() )
        ->toThrow( RuntimeException::class, 'user-owned settings exist' );

    expect( Schema::hasColumn( 'settings', 'user_id' ) )->toBeTrue();
    expect( DB::table( 'settings' )->where( 'id', $setting->id )->first()->value )->toBe( '{"language":"en"}' );
    expect( DB::table( 'settings' )->where( 'id', $setting->id )->value( 'user_id' ) )->toBe( $user->id );
} );

it( 'stops rollback when duplicate setting keys exist without changing them', function () {
    $firstId = DB::table( 'settings' )->insertGetId( [
        'key'        => 'duplicate_key',
        'value'      => 'first',
        'user_id'    => null,
        'created_at' => now(),
        'updated_at' => now(),
    ] );

    $secondId = DB::table( 'settings' )->insertGetId( [
        'key'        => 'duplicate_key',
        'value'      => 'second',
        'user_id'    => null,
        'created_at' => now(),
        'updated_at' => now(),
    ] );

    expect( fn() => settingsIsolationMigration()->down() )
        ->toThrow( RuntimeException::class, 'duplicate keys exist' );

    expect( Schema::hasColumn( 'settings', 'user_id' ) )->toBeTrue();
    expect( DB::table( 'settings' )->where( 'id', $firstId )->value( 'value' ) )->toBe( 'first' );
    expect( DB::table( 'settings' )->where( 'id', $secondId )->value( 'value' ) )->toBe( 'second' );
} );

it( 'keeps the global setting helper separate from user-owned settings', function () {
    $key = 'global_helper_probe';
    $user = createSettingsTestUser( 'settings-helper@example.com' );

    Setting::create( [ 'key' => $key, 'value' => 'global-value' ] );
    $user->settings()->create( [ 'key' => $key, 'value' => 'user-value' ] );

    expect( setting( $key ) )->toBe( 'global-value' );
    expect( setting( 'missing_global_helper_probe', 'fallback' ) )->toBe( 'fallback' );
} );

it( 'rejects invalid settings values', function () {
    $user = createSettingsTestUser( 'settings-validation@example.com' );

    $this->actingAs( $user )
        ->putJson( '/api/settings', [
            'language'      => str_repeat( 'a', 11 ),
            'notifications' => [ 'invoices' => 'not-a-boolean' ],
        ] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( [ 'language', 'notifications.invoices' ] );
} );
