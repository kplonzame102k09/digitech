<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 'STU-'.fake()->unique()->numerify('####-######'),
            'role' => 'student',
            'status' => 'active',
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'middleName' => null,
            'contact' => fake()->numerify('09#########'),
            'birthDate' => fake()->dateTimeBetween('-30 years', '-18 years')->format('Y-m-d'),
            'birthPlace' => fake()->city(),
            'barangay' => fake()->numerify('#########'),
            'city' => fake()->numerify('#########'),
            'province' => fake()->numerify('#########'),
            'region' => fake()->numerify('##'),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
