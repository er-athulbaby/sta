<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase2_sport_totals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('game_sport_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('male')->nullable();
            $t->unsignedSmallInteger('female')->nullable();
            foreach (['gold', 'silver', 'bronze'] as $c) {
                $t->unsignedTinyInteger($c)->nullable();
            }
            $t->timestamps();
            $t->unique(['submission_id', 'game_sport_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase2_sport_totals');
    }
};
