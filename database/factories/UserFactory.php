<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Association;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

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
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Sta,
            'association_id' => null,
            'is_active' => true,
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

    public function superAdmin(): static
    {
        return $this->state(['role' => Role::SuperAdmin]);
    }

    public function sta(): static
    {
        return $this->state(['role' => Role::Sta]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function assocAdmin(?Association $association = null): static
    {
        return $this->state(fn () => ['role' => Role::AssocAdmin, 'association_id' => $association?->id ?? Association::factory()]);
    }

    public function assocEditor(?Association $association = null): static
    {
        return $this->state(fn () => ['role' => Role::AssocEditor, 'association_id' => $association?->id ?? Association::factory()]);
    }
}
