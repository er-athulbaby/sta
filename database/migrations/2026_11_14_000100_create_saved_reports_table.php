<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('subject', 40);
            $t->json('definition');
            $t->string('visibility', 10)->default('private');
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
            $t->index(['created_by', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
