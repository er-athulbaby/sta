<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'association_id' => Association::factory(),
            'phase' => 2,
            'status' => SubmissionStatus::Draft,
        ];
    }
}
