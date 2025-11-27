<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void {
        User::factory()->create(
            [
                'name'     => 'Stefan Jocić',
                'email'    => 'stefan_jocic@hotmail.com',
                'password' => '12345678',
            ]
        );

        $this->call(
            [
                ClientSeeder::class,
                InvoiceSeeder::class,
                InvoiceSettingSeeder::class
            ]
        );
    }
}
