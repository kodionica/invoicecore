<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void {
        $user = User::factory()->create(
            [
                'first_name'     => 'Stefan',
                'last_name'      => 'Jocić',
                'phone'          => '+38169610315',
                'email'          => 'stefan_jocic@hotmail.com',
                'password'       => '12345',
                'remember_token' => 1,
            ]
        );

        Company::factory()->create(
            [
                'name'                => 'Kodionica',
                'tax_id'              => '134524435',
                'registration_number' => '25345345',
                'address'             => 'Branka Ostojića 19',
                'city'                => 'Boljevci',
                'country'             => 'Srbija',
                'email'               => 'kodionica@gmail.com',
                'phone'               => '+38169610315',
                'bank_account'        => '2359249058394',
                'iban'                => 'RS239524598349083i49',
                'swift'               => 'RSBACF',
                'vat_enabled'         => false,
                'user_id'             => $user->id,
            ]
        );

//        $this->call(
//            [
//                ClientSeeder::class,
//                InvoiceSeeder::class,
//                InvoiceSettingSeeder::class,
//            ]
//        );
    }
}
