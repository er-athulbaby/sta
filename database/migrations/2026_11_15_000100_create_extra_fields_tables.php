<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_fields', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('key', 60)->unique();
            $table->string('applies_to', 10);
            $table->string('type', 10);
            $table->json('options')->nullable();
            $table->string('help_text', 255)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('sensitive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('person_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('extra_field_id')->constrained()->restrictOnDelete();
            $table->text('value');
            $table->timestamps();
            $table->unique(['person_id', 'extra_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_field_values');
        Schema::dropIfExists('extra_fields');
    }
};
