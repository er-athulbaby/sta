<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $t) {
            // M6: remarks are two-way; true = a reply written by an association user.
            $t->boolean('from_association')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('comments', fn (Blueprint $t) => $t->dropColumn('from_association'));
    }
};
