<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('clothing_item_id')->constrained()->restrictOnDelete();
            $table->string('size');
            $table->timestamps();
            $table->unique(['person_id', 'clothing_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_sizes');
    }
};
