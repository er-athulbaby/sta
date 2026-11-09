<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Milestone 1 stored free text; the v1.2 form needs a country name and a digits-only number with the code in *_cc. */
return new class extends Migration
{
    private const PHONES = ['phone' => 'phone_cc', 'emergency_phone' => 'emergency_phone_cc', 'guardian_phone' => 'guardian_phone_cc', 'whatsapp' => 'whatsapp_cc'];

    public function up(): void
    {
        $countries = config('countries');
        $codes = array_keys(config('phone_codes'));
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));

        DB::table('people')->orderBy('id')->chunkById(200, function ($rows) use ($countries, $codes) {
            foreach ($rows as $row) {
                $update = [];

                $nationality = trim((string) $row->nationality);
                $name = array_search(strtolower($nationality), array_map('strtolower', $countries), true);
                $match = $name !== false ? $countries[$name] : ($countries[strtoupper($nationality)] ?? null);
                if ($match !== null && $match !== $row->nationality) {
                    $update['nationality'] = $match;
                }

                foreach (self::PHONES as $column => $ccColumn) {
                    $value = $row->{$column};
                    if (blank($value)) {
                        continue;
                    }
                    $cc = $row->{$ccColumn};
                    $number = trim($value);
                    if (preg_match('/^(?:\+|00)\s*(\d.*)$/', $number, $m)) {
                        $digits = $m[1];
                        foreach ($codes as $code) {
                            if (str_starts_with($digits, ltrim($code, '+'))) {
                                $cc = $code;
                                $number = substr($digits, strlen($code) - 1);
                                break;
                            }
                        }
                    }
                    $number = trim(str_replace(['-', '(', ')', '.'], '', $number));
                    if (preg_match('/^[0-9 ]{4,20}$/', $number) && ($number !== $value || $cc !== $row->{$ccColumn})) {
                        $update[$column] = $number;
                        $update[$ccColumn] = $cc;
                    }
                }

                if ($update) {
                    DB::table('people')->where('id', $row->id)->update($update);
                }
            }
        });
    }

    public function down(): void
    {
        // Data clean-up only; the original free text is not kept.
    }
};
