<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('sta')->after('password');
            $table->foreignId('association_id')->nullable()->after('role')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('association_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('association_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
