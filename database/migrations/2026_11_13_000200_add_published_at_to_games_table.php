<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $t) {
            $t->dateTime('published_at')->nullable()->after('reminder_days');
        });
        // Games created before spec 16.6 are live: keep them visible.
        DB::table('games')->update(['published_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $t) {
            $t->dropColumn('published_at');
        });
    }
};
