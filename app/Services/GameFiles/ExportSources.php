<?php

namespace App\Services\GameFiles;

use App\Enums\ExtraFieldType;
use App\Models\ExtraField;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The only sources an export layout column may use (spec 15.2), their kinds and format options. Passport and CPR
 * numbers are allowed (guarded by export_game_files); medical columns and sensitive extra fields need view_medical.
 */
final class ExportSources
{
    /** person column => [label, kind] */
    public const PERSON = [
        'code' => ['Person code', 'text'], 'full_name' => ['Full name', 'text'], 'first_name' => ['First name', 'text'],
        'second_name' => ['Second name', 'text'], 'third_name' => ['Third name', 'text'], 'last_name' => ['Last name', 'text'],
        'dob' => ['Date of birth', 'date'], 'gender' => ['Gender', 'gender'], 'nationality' => ['Nationality', 'text'],
        'height_cm' => ['Height (cm)', 'number'], 'weight_kg' => ['Weight (kg)', 'number'],
        'passport_no' => ['Passport number', 'text'], 'passport_issuing_country' => ['Passport issuing country', 'text'],
        'passport_issue_date' => ['Passport issue date', 'date'], 'passport_expiry' => ['Passport expiry', 'date'],
        'cpr_no' => ['CPR number', 'text'], 'cpr_expiry' => ['CPR expiry', 'date'],
        'function' => ['Function (team officials)', 'text'], 'languages' => ['Languages spoken', 'text'],
        'phone' => ['Mobile', 'text'], 'whatsapp' => ['WhatsApp', 'text'], 'email' => ['Email', 'text'], 'address' => ['Address', 'text'],
        'emergency_name' => ['Emergency contact name', 'text'], 'emergency_relationship' => ['Emergency contact relationship', 'text'],
        'emergency_phone' => ['Emergency phone', 'text'], 'guardian_name' => ['Guardian name', 'text'],
        'guardian_relationship' => ['Guardian relationship', 'text'], 'guardian_phone' => ['Guardian phone', 'text'],
        'guardian_email' => ['Guardian email', 'text'], 'fit' => ['Clothing fit', 'text'],
        'chest_cm' => ['Chest (cm)', 'number'], 'waist_cm' => ['Waist (cm)', 'number'],
        'consent_data' => ['Consent: store personal data', 'yes_no'], 'consent_media' => ['Consent: photos in media', 'yes_no'],
        'consent_medical' => ['Consent: emergency treatment', 'yes_no'], 'decl_conduct' => ['Declaration: code of conduct', 'yes_no'],
        'decl_antidoping' => ['Declaration: anti-doping', 'yes_no'], 'decl_data_sharing' => ['Declaration: data sharing', 'yes_no'],
        'blood_group' => ['Blood group', 'text'], 'meal_requirement' => ['Meal requirement', 'text'],
        'medical_clearance_expiry' => ['Medical clearance expiry', 'date'], 'insurance_policy_no' => ['Insurance policy number', 'text'],
        'allergies' => ['Allergies', 'text'], 'chronic_conditions' => ['Medical conditions', 'text'], 'medication' => ['Medication', 'text'],
    ];

    /** Exported as "{country code}{number}", e.g. +97333445566. */
    public const PHONES = ['phone', 'whatsapp', 'emergency_phone', 'guardian_phone'];

    /** kind => format => label; the first format is the default */
    public const FORMATS = [
        'date' => ['d/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'd-M-Y' => 'DD-MMM-YYYY'],
        'gender' => ['male_female' => 'Male/Female', 'm_f' => 'M/F'],
        'text' => ['as_is' => 'As is', 'upper' => 'UPPER CASE', 'title' => 'Title Case'],
        'yes_no' => ['yes_no' => 'Yes/No', 'y_n' => 'Y/N', '1_0' => '1/0'],
        'number' => [],
    ];

    private const GENDER = ['male_female' => ['male' => 'Male', 'female' => 'Female'], 'm_f' => ['male' => 'M', 'female' => 'F']];

    private const YES_NO = ['yes_no' => ['Yes', 'No'], 'y_n' => ['Y', 'N'], '1_0' => ['1', '0']];

    /** @return array<string, array{label: string, group: string, kind: string, medical: bool, active: bool}> */
    public static function all(): array
    {
        $src = fn (string $label, string $group, string $kind, bool $medical = false, bool $active = true) => compact('label', 'group', 'kind', 'medical', 'active');
        $out = [];
        foreach (self::PERSON as $column => [$label, $kind]) {
            $out["person.{$column}"] = $src($label, 'Person', $kind, in_array($column, Person::MEDICAL_GATED, true));
        }
        // Inactive fields stay resolvable so stored layouts keep working; they are not offered for new columns.
        foreach (ExtraField::orderBy('id')->get() as $f) {
            $out["extra.{$f->key}"] = $src($f->label, 'Additional information', match ($f->type) {
                ExtraFieldType::Date => 'date', ExtraFieldType::Number => 'number', ExtraFieldType::YesNo => 'yes_no', default => 'text',
            }, $f->sensitive, $f->is_active);
        }

        return $out + [
            'entry.sport' => $src('Sport', 'Entry', 'text'),
            'entry.event' => $src('Event', 'Entry', 'text'),
            'entry.event_gender' => $src('Event gender', 'Entry', 'text'),
            'game.age' => $src('Age at game start', 'Game', 'number'),
            'association.code' => $src('Association code', 'Association', 'text'),
            'association.name' => $src('Association name', 'Association', 'text'),
            'text' => $src('Fixed text', 'Other', 'text'),
        ];
    }

    /**
     * @param  list<string>  $keep  sources already in the stored layout (kept even when the user could not add them)
     * @return array<string, array<string, string>> group => source => label
     */
    public static function options(?User $user, array $keep = []): array
    {
        $medic = Person::canViewMedical($user);
        $options = [];
        foreach (self::all() as $key => $s) {
            if (in_array($key, $keep, true) || ($s['active'] && ($medic || ! $s['medical']))) {
                $options[$s['group']][$key] = $s['label'];
            }
        }

        return $options;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<string>  $keep  sources already in the stored layout
     * @return list<string> one line per problem; [] when valid
     */
    public static function validate(array $columns, ?User $user, array $keep = []): array
    {
        $all = self::all();
        $errors = [];
        foreach (array_values($columns) as $i => $c) {
            $line = fn (string $message) => 'Column '.($i + 1).': '.$message;
            $s = $all[$c['source'] ?? ''] ?? null;
            if (! $s) {
                $errors[] = $line('choose a source from the list.');

                continue;
            }
            if ($s['medical'] && ! Person::canViewMedical($user) && ! in_array($c['source'], $keep, true)) {
                $errors[] = $line('only users with "View medical & health data" can add this column.');
            }
            if (blank($c['heading'] ?? null) || mb_strlen((string) $c['heading']) > 100) {
                $errors[] = $line('give a heading of up to 100 characters.');
            }
            if (filled($c['format'] ?? null) && ! array_key_exists($c['format'], self::FORMATS[$s['kind']])) {
                $errors[] = $line('choose a format from the list.');
            }
            if ($c['source'] === 'text' && blank($c['value'] ?? null)) {
                $errors[] = $line('enter the fixed text.');
            }
        }

        return $errors;
    }

    public static function format(mixed $value, string $kind, ?string $format): string|int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($kind) {
            'date' => Carbon::parse($value)->format($format ?: 'd/m/Y'),
            'gender' => self::GENDER[$format ?: 'male_female'][$value] ?? (string) $value,
            'yes_no' => self::YES_NO[$format ?: 'yes_no'][$value ? 0 : 1],
            'number' => is_numeric($value) ? $value + 0 : (string) $value,
            default => match ($format) {
                'upper' => mb_strtoupper((string) $value),
                'title' => mb_convert_case(mb_strtolower((string) $value), MB_CASE_TITLE),
                default => (string) $value,
            },
        };
    }
}
