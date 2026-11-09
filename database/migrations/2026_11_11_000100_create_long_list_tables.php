<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $t->foreignId('game_event_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('game_sport_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            // Officials have game_event_id null, which a unique index does not compare; the Long List save keeps them unique.
            $t->unique(['submission_id', 'person_id', 'game_event_id', 'game_sport_id']);
        });
        Schema::create('long_list_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            // ponytail: a DB cascade from a deleted person leaves the certificate file behind; delete it in Person::deleted if that matters.
            $t->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $t->string('insurance_company')->nullable();
            $t->text('insurance_policy_no')->nullable(); // encrypted cast
            $t->date('insurance_valid_until')->nullable();
            $t->string('insurance_certificate_path')->nullable();
            $t->text('vaccinations')->nullable();
            $t->timestamps();
            $t->unique(['submission_id', 'person_id']);
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['comments', 'long_list_details', 'entries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
