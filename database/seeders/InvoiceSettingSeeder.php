<?php

namespace Database\Seeders;

use App\Models\InvoiceSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoiceSettingSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $user = User::first();

        InvoiceSetting::create(
            [
                'user_id'          => $user->id,
                'company_name'     => 'Kodionica',
                'company_address'  => 'Belgrade, Serbia',
                'company_email'    => 'kodionica@gmail.com',
                'company_phone'    => '+38169610315',
                'iban'             => 'RS35123456789012345678',
                'swift'            => 'KOBBRS22',
                'default_currency' => 'EUR',
                'default_due_days' => 15,
                'footer_note'      => 'Thank you for your business!',
            ]
        );
    }
}
