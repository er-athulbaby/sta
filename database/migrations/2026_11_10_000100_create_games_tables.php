<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('logo_path')->nullable();
            $t->date('start_date');
            $t->date('end_date');
            $t->json('reminder_days');
            $t->timestamps();
        });
        Schema::create('game_associations', function (Blueprint $t) {
            $t->foreignId('game_id')->constrained()->cascadeOnDelete();
            $t->foreignId('association_id')->constrained()->cascadeOnDelete();
            $t->primary(['game_id', 'association_id']);
        });
        Schema::create('game_sports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('game_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sport_id')->constrained();
            foreach (['p2_max_men', 'p2_max_women', 'p2_max_total', 'p4_max_men', 'p4_max_women', 'p4_max_officials'] as $c) {
                $t->unsignedSmallInteger($c)->nullable();
            }
            $t->date('dob_from')->nullable();
            $t->date('dob_to')->nullable();
            $t->text('entry_policy')->nullable();
            $t->timestamps();
            $t->unique(['game_id', 'sport_id']);
        });
        Schema::create('game_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('game_id')->constrained()->cascadeOnDelete();
            $t->foreignId('game_sport_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_id')->constrained();
            $t->unsignedSmallInteger('p2_max_athletes')->nullable();
            $t->timestamps();
            $t->unique(['game_id', 'event_id']);
        });
        Schema::create('game_clothing_items', function (Blueprint $t) {
            $t->foreignId('game_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clothing_item_id')->constrained();
            $t->primary(['game_id', 'clothing_item_id']);
        });
        Schema::create('game_phases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('game_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('phase');
            $t->boolean('enabled')->default(false);
            $t->string('status', 10)->default('draft');
            $t->dateTime('opens_at')->nullable();
            $t->dateTime('deadline')->nullable();
            $t->timestamps();
            $t->unique(['game_id', 'phase']);
        });
    }

    public function down(): void
    {
        foreach (['game_phases', 'game_clothing_items', 'game_events', 'game_sports', 'game_associations', 'games'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
