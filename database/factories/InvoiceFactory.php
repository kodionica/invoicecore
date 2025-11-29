<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        $user   = User::all()->random();
        $client = Client::all()->random();

        return [
            'user_id'        => $user->id,
            'client_id'      => $client->id,
            'invoice_number' => $this->faker->unique()->numerify( 'INV-####' ),
            'invoice_date'   => now(),
            'due_date'       => now()->addDays( 15 ),
            'currency'       => $this->faker->currencyCode(),
            'total_amount'   => $this->faker->randomFloat( 0, 0, 9999 ),
        ];
    }
}
