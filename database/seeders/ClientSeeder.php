<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $company = Company::firstOrFail();

        Client::create(
            [
                'company_id' => $company->id,
                'name'       => 'Acme Corp',
                'email'      => 'billing@acme.com',
                'address'    => 'Berlin, Germany',
                'country'    => 'DE',
                'country_code' => 'DE',
                'tax_id' => 'DE123456789',
            ]
        );
    }
}
