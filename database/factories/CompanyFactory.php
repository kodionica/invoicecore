<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'name'                => fake()->company,
            'tax_id'              => fake()->randomNumber( 9 ),
            'registration_number' => fake()->randomNumber( 6 ),
            'address'             => fake()->address(),
            'city'                => fake()->city(),
            'country'             => fake()->country(),
            'email'               => fake()->companyEmail(),
            'phone'               => fake()->phoneNumber(),
            'bank_account'        => fake()->randomNumber( 12 ),
            'iban'                => fake()->iban(),
            'swift'               => fake()->swiftBicNumber(),
            'logo_path'           => '',
            'currency'            => fake()->currencyCode(),
            'vat_enabled'         => fake()->boolean(),
            'user_id'             => User::get()->random()->id,
        ];
    }
}
