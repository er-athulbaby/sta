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
        return $this->state(['type' => PersonType::Official, 'function' => 'Head Coach', 'phone' => '33000000', 'email' => fake()->safeEmail()]);
    }

    public function complete(): static
    {
        return $this->state(fn () => ['full_name' => 'Test Person', 'photo_path' => 'photos/x.jpg', 'passport_issuing_country' => 'Bahrain',
            'passport_issue_date' => now()->subYear()->toDateString(), 'passport_scan_path' => 'scans/x.pdf', 'phone' => '33000000',
            'whatsapp_same' => true, 'email' => fake()->safeEmail(), 'emergency_name' => 'Kin', 'emergency_relationship' => 'Brother',
            'emergency_phone' => '33111111']);
    }
}
