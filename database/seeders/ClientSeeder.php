<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $user = User::first();

        Client::create(
            [
                'user_id'    => $user?->id,
                'name'       => 'Acme Corp',
                'email'      => 'billing@acme.com',
                'address'    => 'Berlin, Germany',
                'country'    => 'DE',
                'vat_number' => 'DE123456789',
            ]
        );
    }
}
