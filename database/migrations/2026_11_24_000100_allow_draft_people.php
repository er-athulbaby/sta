<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Save as draft" on the person form needs only the names; the rest stays empty until the final Save (Person::missingFields()). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->text('passport_no')->nullable()->change();
            $table->string('passport_no_hash', 64)->nullable()->change();
            $table->date('passport_expiry')->nullable()->change();
            $table->string('nationality')->nullable()->change();
            $table->date('dob')->nullable()->change();
            $table->string('gender')->nullable()->change();
        });
    }

    public function down(): void
    {
        // ponytail: one-way; rows saved as drafts would break NOT NULL. Restore from backup if ever needed.
    }
};
