<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $t) {
            $t->unsignedInteger('lock_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $t) {
            $t->dropColumn('lock_version');
        });
    }
};
