<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associations', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('name');
        });
        DB::table('associations')->whereNull('code')->orderBy('id')->each(
            fn ($a) => DB::table('associations')->where('id', $a->id)->update(['code' => 'A'.$a->id])
        );
        Schema::table('associations', function (Blueprint $table) {
            $table->string('code', 10)->nullable(false)->change();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('associations', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
