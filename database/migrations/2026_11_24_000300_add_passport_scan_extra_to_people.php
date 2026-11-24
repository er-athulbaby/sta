<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** More passport pages: passport_scan_path stays the main scan, the other pages are a JSON list of paths. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->json('passport_scan_extra')->nullable()->after('passport_scan_path');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('passport_scan_extra');
        });
    }
};
