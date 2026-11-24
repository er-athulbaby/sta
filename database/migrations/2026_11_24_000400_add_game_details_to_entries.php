<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The "Add to Long List" pop-up: a team official's function in this game, and who confirmed the passport is the latest one. */
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $t) {
            $t->string('function')->nullable()->after('game_sport_id');
            $t->timestamp('passport_confirmed_at')->nullable()->after('function');
            $t->foreignId('passport_confirmed_by')->nullable()->after('passport_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $t) {
            $t->dropConstrainedForeignId('passport_confirmed_by');
            $t->dropColumn(['function', 'passport_confirmed_at']);
        });
    }
};
