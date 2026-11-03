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
        return [
            'company_id' => function (): int {
                $owner = User::factory()->create(['username' => $this->faker->unique()->userName()]);

                return $owner->companies()->create(['name' => $this->faker->company(), 'currency' => 'RSD'])->id;
            },
            'name'       => $this->faker->company(),
            'email'      => $this->faker->unique()->safeEmail(),
            'address'    => $this->faker->address(),
            'country_code' => 'RS',
            'client_type' => 'company',
        ];
    }
}
