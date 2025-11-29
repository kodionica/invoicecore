<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        $invoice = Invoice::all()->random();

        return [
            'invoice_id'   => $invoice->id,
            'amount'       => $invoice->total_amount,
            'payment_date' => $invoice->due_date->addDays( $this->faker->numberBetween( 1, 10 ) ),
            'method'       => $this->faker->randomElement( [ 'Credit Card', 'Bank Transfer', 'PayPal', 'Cash' ] ),
        ];
    }
}
