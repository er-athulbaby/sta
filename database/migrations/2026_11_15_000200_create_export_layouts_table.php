<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('file_format', 4)->default('xlsx');
            $table->string('row_mode', 20)->default('one_per_athlete');
            $table->json('columns');
            $table->string('zip_pattern', 200)->default('{code}_{kind}');
            $table->timestamps();
            $table->unique(['game_id', 'type']);
        });

        // Permissions live in the database; production gets the new one here (PermissionSeeder also creates it).
        Permission::findOrCreate('export_game_files', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('export_layouts');
        Permission::where('name', 'export_game_files')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
