<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('association_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('first_name');
            $table->string('second_name')->nullable();
            $table->string('third_name')->nullable();
            $table->string('last_name');
            $table->string('photo_path')->nullable();
            $table->text('passport_no');
            $table->string('passport_no_hash', 64)->unique();
            $table->date('passport_expiry');
            $table->string('passport_scan_path')->nullable();
            $table->text('cpr_no')->nullable();
            $table->string('cpr_no_hash', 64)->nullable()->unique();
            $table->date('cpr_expiry')->nullable();
            $table->string('cpr_scan_path')->nullable();
            $table->string('nationality');
            $table->date('dob');
            $table->string('gender');
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->string('function')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['association_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
