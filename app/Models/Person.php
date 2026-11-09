<?php

namespace App\Models;

use App\Enums\AppliesTo;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Models\Concerns\BelongsToAssociation;
use App\Models\Concerns\RenamesAttachments;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Person extends Model
{
    use BelongsToAssociation, HasFactory, LogsActivity, RenamesAttachments;

    public const FILE_COLUMNS = ['photo_path' => 'photo', 'passport_scan_path' => 'passport', 'cpr_front_path' => 'cpr_front',
        'cpr_back_path' => 'cpr_back', 'cpr_reader_path' => 'cpr_reader', 'wada_certificate_path' => 'wada'];

    public const ATHLETE_ONLY = ['address', 'guardian_name', 'guardian_relationship', 'guardian_phone_cc', 'guardian_phone', 'guardian_email',
        'wada_certificate_path', 'medical_clearance_expiry', 'insurance_policy_no', 'consent_data', 'consent_media', 'consent_medical'];

    public const OFFICIAL_ONLY = ['function', 'languages', 'meal_requirement', 'decl_conduct', 'decl_antidoping', 'decl_data_sharing'];

    /** Encrypted, unlogged columns. */
    public const MEDICAL = ['blood_group', 'insurance_policy_no', 'allergies', 'chronic_conditions', 'medication'];

    public const CONSENTS = ['consent_data', 'consent_media', 'consent_medical', 'decl_conduct', 'decl_antidoping', 'decl_data_sharing'];

    public const REQUIRED_FOR_COMPLETE = [
        'athlete' => ['full_name' => 'Full name', 'photo_path' => 'Photo', 'passport_issuing_country' => 'Passport issuing country',
            'passport_issue_date' => 'Passport issue date', 'passport_scan_path' => 'Passport scan', 'cpr_no' => 'CPR number'],
        'official' => ['full_name' => 'Full name', 'photo_path' => 'Photo', 'passport_issuing_country' => 'Passport issuing country',
            'passport_issue_date' => 'Passport issue date', 'passport_scan_path' => 'Passport scan', 'phone' => 'Mobile',
            'whatsapp' => 'WhatsApp', 'email' => 'Email', 'emergency_name' => 'Emergency contact name',
            'emergency_relationship' => 'Emergency contact relationship', 'emergency_phone' => 'Emergency phone'],
    ];

    protected $fillable = [
        'association_id', 'type', 'first_name', 'second_name', 'third_name', 'last_name', 'photo_path',
        'passport_no', 'passport_expiry', 'passport_scan_path', 'cpr_no', 'cpr_expiry', 'cpr_front_path',
        'nationality', 'dob', 'gender', 'height_cm', 'weight_kg', 'function', 'phone', 'email', 'is_active',
        'full_name', 'passport_issuing_country', 'passport_issue_date', 'cpr_back_path', 'cpr_reader_path',
        'wada_certificate_path', 'fit', 'chest_cm', 'waist_cm', 'phone_cc', 'whatsapp_same', 'whatsapp_cc', 'whatsapp',
        'address', 'emergency_name', 'emergency_relationship', 'emergency_phone_cc', 'emergency_phone',
        'guardian_name', 'guardian_relationship', 'guardian_phone_cc', 'guardian_phone', 'guardian_email',
        'languages', 'blood_group', 'medical_clearance_expiry', 'insurance_policy_no', 'allergies',
        'chronic_conditions', 'medication', 'meal_requirement',
        'consent_data', 'consent_media', 'consent_medical', 'decl_conduct', 'decl_antidoping', 'decl_data_sharing',
    ];

    /** Mirror the DB defaults of the NOT NULL *_cc columns. */
    protected $attributes = ['phone_cc' => '+973', 'whatsapp_cc' => '+973', 'emergency_phone_cc' => '+973', 'guardian_phone_cc' => '+973'];

    protected $hidden = ['passport_no_hash', 'cpr_no_hash'];

    /** @var array<int, string> */
    public array $visaPathsToDelete = [];

    protected function casts(): array
    {
        return [
            ...array_fill_keys(self::MEDICAL, 'encrypted'),
            ...array_fill_keys(self::CONSENTS, 'boolean'),
            ...array_fill_keys(array_map(fn ($c) => $c.'_at', self::CONSENTS), 'datetime'),
            'passport_issue_date' => 'date',
            'medical_clearance_expiry' => 'date',
            'whatsapp_same' => 'boolean',
            'type' => PersonType::class,
            'gender' => PersonGender::class,
            'passport_no' => 'encrypted',
            'cpr_no' => 'encrypted',
            'passport_expiry' => 'date',
            'cpr_expiry' => 'date',
            'dob' => 'date',
            'weight_kg' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Person $person) {
            $person->passport_no_hash = self::blindIndex($person->passport_no);
            $person->cpr_no_hash = filled($person->cpr_no) ? self::blindIndex($person->cpr_no) : null;
            $clear = $person->type === PersonType::Athlete ? self::OFFICIAL_ONLY : self::ATHLETE_ONLY;
            foreach ($clear as $column) {
                // ponytail: *_cc columns are NOT NULL with a +973 default, so reset them to that instead of null.
                $person->{$column} = in_array($column, self::CONSENTS, true) ? false : (str_ends_with($column, '_cc') ? '+973' : null);
            }
            if ($person->whatsapp_same) {
                $person->whatsapp_cc = $person->phone_cc;
                $person->whatsapp = $person->phone;
            }
            foreach (self::CONSENTS as $column) {
                if ($person->isDirty($column)) {
                    $person->{$column.'_at'} = $person->{$column} ? now() : null;
                }
            }
        });

        // Deleted people are hard-deleted, so the counter lives on the association (row-locked) to never reuse a number.
        static::creating(function (Person $person) {
            DB::transaction(function () use ($person) {
                $association = Association::query()->whereKey($person->association_id)->lockForUpdate()->firstOrFail();
                $official = $person->type === PersonType::Official;
                $column = $official ? 'last_official_seq' : 'last_athlete_seq';
                $seq = $association->{$column} + 1;
                $association->forceFill([$column => $seq])->saveQuietly();
                $person->seq = $seq;
                $person->code = sprintf('%s-%s-%04d', $association->code, $official ? 'TO' : 'AT', $seq);
            });
        });

        // Sizes of items that no longer apply to the new type go stale; deactivated items are kept on purpose.
        static::saved(function (Person $person) {
            if ($person->wasChanged('type')) {
                $person->sizes()->whereHas('clothingItem', fn ($q) => $q->whereNotIn('applies_to', [AppliesTo::Both->value, $person->type->value]))->delete();
            }
        });

        // Visas cascade in the DB, so their file paths must be read before the row goes.
        static::deleting(function (Person $person) {
            $person->visaPathsToDelete = $person->visas()->pluck('copy_path')->filter()->all();
        });

        static::deleted(function (Person $person) {
            $paths = array_filter(array_map(fn ($c) => $person->{$c}, array_keys(self::FILE_COLUMNS)));
            Storage::disk(config('filesystems.private'))->delete([...$paths, ...$person->visaPathsToDelete]);
        });
    }

    protected function attachmentColumns(): array
    {
        return self::FILE_COLUMNS;
    }

    protected function attachmentPrefix(): string
    {
        return $this->code;
    }

    // ponytail: HMAC keyed by APP_KEY; rotating APP_KEY requires re-computing both *_hash columns (artisan command when needed).
    public static function blindIndex(string $number): string
    {
        return hash_hmac('sha256', strtoupper(preg_replace('/\s+/', '', $number)), config('app.key'));
    }

    /** System-wide check (bypasses the association scope and Filament's tenant scope) used by form validation. */
    public static function documentExists(string $hashColumn, string $number, ?int $ignoreId = null): bool
    {
        return static::withoutGlobalScopes()
            ->where($hashColumn, self::blindIndex($number))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    public function visas(): HasMany
    {
        return $this->hasMany(PersonVisa::class);
    }

    public function injuries(): HasMany
    {
        return $this->hasMany(PersonInjury::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(PersonSize::class);
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->second_name, $this->third_name, $this->last_name])->filter()->implode(' ');
    }

    public function age(?CarbonInterface $on = null): int
    {
        return (int) $this->dob->diffInYears($on ?? now());
    }

    /** @return array<int, string> clothing_item_id => size */
    public function sizesMap(): array
    {
        return $this->sizes()->pluck('size', 'clothing_item_id')->all();
    }

    /** @param array<int|string, ?string> $sizes clothing_item_id => size (null removes) */
    public function syncSizes(array $sizes): void
    {
        $items = ClothingItem::whereKey(array_keys($sizes))->get()->keyBy('id');

        DB::transaction(function () use ($sizes, $items) {
            foreach ($sizes as $itemId => $size) {
                if (blank($size)) {
                    $this->sizes()->where('clothing_item_id', $itemId)->delete();

                    continue;
                }
                $item = $items->get((int) $itemId);
                if (! $item || ! $item->applies_to->includes($this->type) || ! $item->hasSize($size)) {
                    throw ValidationException::withMessages(["sizes.$itemId" => 'Choose a size from the list for this item.']);
                }
                $this->sizes()->updateOrCreate(['clothing_item_id' => $item->id], ['size' => $size]);
            }
        });
    }

    /** @return array<int, string> */
    public function missingFields(): array
    {
        return collect(self::REQUIRED_FOR_COMPLETE[$this->type->value])
            ->filter(fn (string $label, string $column) => blank($this->{$column}))->values()->all();
    }

    public function isComplete(): bool
    {
        return $this->missingFields() === [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['passport_no', 'cpr_no', ...self::MEDICAL])
            ->logOnlyDirty();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $changed = array_keys(array_filter([
            'passport_no' => $this->wasChanged('passport_no_hash'),
            'cpr_no' => $this->wasChanged('cpr_no_hash'),
        ]));
        if ($eventName === 'updated' && $changed) {
            $activity->properties = $activity->properties->put('documents_changed', $changed);
        }
        if ($eventName === 'updated' && collect(self::MEDICAL)->contains(fn ($c) => $this->wasChanged($c))) {
            $activity->properties = $activity->properties->put('medical_changed', true);
        }
    }

    public static function canViewMedical(?User $user): bool
    {
        return $user !== null && ($user->role->isAssociationRole() || $user->can(StaPermission::ViewMedical->value));
    }
}
