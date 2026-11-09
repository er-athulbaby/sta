<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('game_id')->constrained()->cascadeOnDelete();
            $t->foreignId('association_id')->constrained();
            $t->unsignedTinyInteger('phase');
            $t->string('status', 30)->default('draft');
            $t->string('reference_no', 40)->nullable()->unique();
            $t->unsignedSmallInteger('revision')->default(0);
            $t->dateTime('deadline_override')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('submitted_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('approved_at')->nullable();
            $t->json('pdf_paths')->nullable();
            $t->text('justification')->nullable();
            $t->timestamps();
            $t->unique(['game_id', 'association_id', 'phase']);
        });
        Schema::create('interest_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('game_sport_id')->constrained()->cascadeOnDelete();
            $t->string('answer', 5);
            $t->timestamps();
            $t->unique(['submission_id', 'game_sport_id']);
        });
        Schema::create('number_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('game_event_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('count')->default(0);
            $t->timestamps();
            $t->unique(['submission_id', 'game_event_id']);
        });
    }

    public function down(): void
    {
        foreach (['number_entries', 'interest_answers', 'submissions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
