<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('gender');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['sport_id', 'name', 'gender']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
