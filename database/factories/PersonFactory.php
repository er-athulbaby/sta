<?php

namespace Database\Factories;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\Association;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'association_id' => Association::factory(),
            'type' => PersonType::Athlete,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'passport_no' => strtoupper(fake()->unique()->bothify('??#######')),
            'passport_expiry' => now()->addYears(3)->toDateString(),
            'cpr_no' => fake()->unique()->numerify('#########'),
            'cpr_expiry' => now()->addYears(2)->toDateString(),
            'nationality' => 'Bahrain',
            'dob' => '2005-03-10',
            'gender' => PersonGender::Male,
            'height_cm' => 178,
            'weight_kg' => 72.5,
        ];
    }

    public function official(): static
    {
        return $this->state(['type' => PersonType::Official, 'function' => 'Coach', 'phone' => '+97333000000', 'email' => fake()->safeEmail()]);
    }
}
