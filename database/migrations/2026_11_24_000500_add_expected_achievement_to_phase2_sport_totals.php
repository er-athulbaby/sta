<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Phase 2: what the association expects to achieve in a sport (required on submit when no medal is expected). */
    public function up(): void
    {
        Schema::table('phase2_sport_totals', function (Blueprint $t) {
            $t->string('expected_achievement', 500)->nullable()->after('bronze');
        });
    }

    public function down(): void
    {
        Schema::table('phase2_sport_totals', function (Blueprint $t) {
            $t->dropColumn('expected_achievement');
        });
    }
};
