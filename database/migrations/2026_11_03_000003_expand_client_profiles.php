<?php

use App\Support\ClientProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $aliases = [];
        foreach (DB::table('clients')->whereNotNull('country')->distinct()->pluck('country') as $value) {
            $code = ClientProfile::recognizeCountry($value);
            if ($code !== null) {
                $aliases[$code][] = $value;
            }
        }
        foreach ($aliases as $code => $values) {
            foreach (['tax_id', 'registration_number'] as $field) {
                $conflict = DB::table('clients')->whereIn('country', $values)->whereNotNull($field)
                    ->selectRaw('company_id, MIN(id) AS first_id, MAX(id) AS last_id')
                    ->groupBy('company_id', $field)->havingRaw('COUNT(*) > 1')->first();
                if ($conflict) {
                    throw new RuntimeException("Client identifier conflict on {$field} ({$code}) for clients {$conflict->first_id} and {$conflict->last_id}.");
                }
            }
        }

        Schema::table('clients', static function (Blueprint $table): void {
            $table->string('client_type', 20)->default('company')->change();
            $table->char('country_code', 2)->nullable()->default('RS');
            $table->string('postal_code')->nullable();
            $table->string('contact_person')->nullable();
            $table->char('default_currency', 3)->nullable();
            $table->string('preferred_language')->nullable();
        });
        foreach (DB::table('clients')->select('id', 'country', 'client_type')->lazyById() as $client) {
            DB::table('clients')->where('id', $client->id)->update([
                'client_type' => match ($client->client_type) {
                    'b2b' => 'company', 'b2c' => 'individual', default => $client->client_type,
                },
                'country_code' => ClientProfile::recognizeCountry($client->country),
            ]);
        }
        Schema::table('clients', static function (Blueprint $table): void {
            $table->unique(['company_id', 'country_code', 'tax_id'], 'clients_country_tax_unique');
            $table->unique(['company_id', 'country_code', 'registration_number'], 'clients_country_registration_unique');
            $table->dropUnique(['company_id', 'tax_id', 'registration_number']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Client profiles cannot be reverted without losing data.');
    }
};
