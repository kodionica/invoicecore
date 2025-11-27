<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $user   = User::first();
        $client = Client::first();

        $invoice = Invoice::create(
            [
                'user_id'        => $user->id,
                'client_id'      => $client->id,
                'invoice_number' => 'INV-0001',
                'invoice_date'   => now(),
                'due_date'       => now()->addDays( 15 ),
                'currency'       => 'EUR',
                'total_amount'   => 500,
            ]
        );

        InvoiceItem::create(
            [
                'invoice_id'  => $invoice->id,
                'description' => 'Software development services',
                'quantity'    => 10,
                'unit_price'  => 50,
                'total'       => 500,
            ]
        );

        Payment::create(
            [
                'invoice_id'   => $invoice->id,
                'amount'       => 500,
                'payment_date' => now()->subDays( 2 ),
                'method'       => 'Bank transfer',
            ]
        );
    }
}
