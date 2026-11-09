<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSENTS = ['consent_data', 'consent_media', 'consent_medical', 'decl_conduct', 'decl_antidoping', 'decl_data_sharing'];

    private const COLUMNS = [
        'code', 'seq', 'full_name', 'passport_issuing_country', 'passport_issue_date', 'cpr_back_path', 'cpr_reader_path',
        'wada_certificate_path', 'fit', 'chest_cm', 'waist_cm', 'phone_cc', 'whatsapp_same', 'whatsapp_cc', 'whatsapp',
        'address', 'emergency_name', 'emergency_relationship', 'emergency_phone_cc', 'emergency_phone',
        'guardian_name', 'guardian_relationship', 'guardian_phone_cc', 'guardian_phone', 'guardian_email',
        'languages', 'blood_group', 'medical_clearance_expiry', 'insurance_policy_no', 'allergies',
        'chronic_conditions', 'medication', 'meal_requirement',
    ];

    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->renameColumn('cpr_scan_path', 'cpr_front_path');
        });
        Schema::table('people', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->unique()->after('id');
            $table->unsignedInteger('seq')->nullable()->after('code');
            $table->string('full_name')->nullable()->after('type');
            $table->string('passport_issuing_country', 100)->nullable()->after('passport_no_hash');
            $table->date('passport_issue_date')->nullable()->after('passport_issuing_country');
            $table->string('cpr_back_path')->nullable()->after('cpr_front_path');
            $table->string('cpr_reader_path')->nullable()->after('cpr_back_path');
            $table->string('wada_certificate_path')->nullable();
            $table->string('fit', 10)->nullable();
            $table->unsignedSmallInteger('chest_cm')->nullable();
            $table->unsignedSmallInteger('waist_cm')->nullable();
            $table->string('phone_cc', 6)->default('+973');
            $table->boolean('whatsapp_same')->default(false);
            $table->string('whatsapp_cc', 6)->default('+973');
            $table->string('whatsapp', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_relationship', 100)->nullable();
            $table->string('emergency_phone_cc', 6)->default('+973');
            $table->string('emergency_phone', 50)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship', 30)->nullable();
            $table->string('guardian_phone_cc', 6)->default('+973');
            $table->string('guardian_phone', 50)->nullable();
            $table->string('guardian_email')->nullable();
            $table->string('languages')->nullable();
            $table->text('blood_group')->nullable();
            $table->date('medical_clearance_expiry')->nullable();
            $table->text('insurance_policy_no')->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->text('medication')->nullable();
            $table->string('meal_requirement', 30)->nullable();
            foreach (self::CONSENTS as $c) {
                $table->boolean($c)->default(false);
                $table->timestamp($c.'_at')->nullable();
            }
            $table->unique(['association_id', 'type', 'seq']);
        });
        Schema::table('associations', function (Blueprint $table) {
            $table->unsignedInteger('last_athlete_seq')->default(0);
            $table->unsignedInteger('last_official_seq')->default(0);
        });

        $counters = [];
        DB::table('people')->orderBy('id')->each(function ($p) use (&$counters) {
            $assoc = DB::table('associations')->where('id', $p->association_id)->value('code');
            $key = $p->association_id.'|'.$p->type;
            $seq = $counters[$key] = ($counters[$key] ?? 0) + 1;
            DB::table('people')->where('id', $p->id)->update([
                'seq' => $seq,
                'code' => sprintf('%s-%s-%04d', $assoc, $p->type === 'official' ? 'TO' : 'AT', $seq),
            ]);
        });
        foreach ($counters as $key => $seq) {
            [$assocId, $type] = explode('|', $key);
            DB::table('associations')->where('id', $assocId)->update([$type === 'official' ? 'last_official_seq' : 'last_athlete_seq' => $seq]);
        }
    }

    public function down(): void
    {
        Schema::table('associations', function (Blueprint $table) {
            $table->dropColumn(['last_athlete_seq', 'last_official_seq']);
        });
        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique(['association_id', 'type', 'seq']);
            $table->dropUnique(['code']);
        });
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([...self::COLUMNS, ...self::CONSENTS, ...array_map(fn ($c) => $c.'_at', self::CONSENTS)]);
        });
        Schema::table('people', function (Blueprint $table) {
            $table->renameColumn('cpr_front_path', 'cpr_scan_path');
        });
    }
};
