<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Spec 17: an optional note per Phase 1 answer, printed on the Entry by Sport PDF. */
    public function up(): void
    {
        Schema::table('interest_answers', function (Blueprint $t) {
            $t->string('note', 255)->nullable()->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('interest_answers', function (Blueprint $t) {
            $t->dropColumn('note');
        });
    }
};
