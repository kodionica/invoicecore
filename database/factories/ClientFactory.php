<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        $user = User::first();

        return [
            'user_id'    => $user?->id,
            'name'       => $this->faker->company(),
            'email'      => $this->faker->unique()->safeEmail(),
            'address'    => $this->faker->address(),
            'country'    => $this->faker->country(),
            'vat_number' => $this->faker->bothify( '??#########' ),
        ];
    }
}
