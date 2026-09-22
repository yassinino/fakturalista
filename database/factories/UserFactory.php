<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            // Matches the `locale` column's DB default ('es'). Explicit here
            // because App\Http\Middleware\SetLocale falls back to
            // $request->session() when the user's locale is empty/unsupported,
            // and API routes carry no session middleware at all - an
            // in-memory factory user used via actingAs() (never re-fetched
            // from the DB) would otherwise have a null locale in PHP even
            // though the column's DB default is populated, crashing every
            // authenticated API request in tests with a 500. Pre-existing
            // middleware fragility, flagged separately - not fixed here.
            'locale' => 'es',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
