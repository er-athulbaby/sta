<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameSportFactory extends Factory
{
    public function definition(): array
    {
        return ['game_id' => Game::factory(), 'sport_id' => Sport::factory()];
    }
}
