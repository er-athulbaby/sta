<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique(); // people.guardian_relationship is a 30-character column
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seeded here (not in a seeder) so existing installs get the list on migrate.
        $names = ['Father', 'Mother', 'Brother', 'Sister', 'Spouse', 'Son', 'Daughter', 'Uncle', 'Aunt', 'Cousin', 'Guardian', 'Friend', 'Coach', 'Other'];
        DB::table('relationships')->insert(array_map(fn (string $name, int $i) => [
            'name' => $name, 'is_active' => true, 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now(),
        ], $names, array_keys($names)));
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
