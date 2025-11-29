<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        $invoice = Invoice::all()->random();

        return [
            'invoice_id'  => $invoice->id,
            'description' => $this->faker->sentence( 6 ),
            'quantity'    => $this->faker->numberBetween( 1, 10 ),
            'unit_price'  => $this->faker->numberBetween( 20, 100 ),
            'total'       => function ( array $attributes ) {
                return $attributes[ 'quantity' ] * $attributes[ 'unit_price' ];
            },
        ];
    }
}
