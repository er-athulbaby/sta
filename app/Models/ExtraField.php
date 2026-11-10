<?php

namespace App\Models;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Enums\PersonType;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** An STA-defined person field (spec 15.1). Never deleted; deactivating hides it and keeps its values. */
class ExtraField extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['label', 'applies_to', 'type', 'options', 'help_text', 'required', 'sensitive', 'is_active'];

    protected function casts(): array
    {
        return ['applies_to' => AppliesTo::class, 'type' => ExtraFieldType::class, 'options' => 'array',
            'required' => 'boolean', 'sensitive' => 'boolean', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The key is used by the Excel import and export layouts, so it is generated once and never changes.
        static::creating(function (ExtraField $field) {
            $base = Str::limit(Str::slug($field->label, '_'), 50, '') ?: 'field';
            $key = $base;
            for ($n = 2; static::where('key', $key)->exists(); $n++) {
                $key = "{$base}_{$n}";
            }
            $field->key = $key;
        });

        static::saving(function (ExtraField $field) {
            if ($field->type !== ExtraFieldType::Choice) {
                $field->options = null;
            }
        });

        static::updating(function (ExtraField $field) {
            if ($field->isDirty('type') && $field->values()->exists()) {
                throw ValidationException::withMessages(['type' => 'The type cannot change once people have values for this field.']);
            }
        });

        // requiredFor() is memoised per request; any definition change must be seen at once.
        static::saved(fn () => Once::flush());
    }

    public function values(): HasMany
    {
        return $this->hasMany(PersonFieldValue::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, PersonType $type): void
    {
        $query->whereIn('applies_to', [AppliesTo::Both->value, $type->value]);
    }

    /** Sensitive fields follow the medical rule (spec 15.1, 13). */
    public function visibleTo(?User $user): bool
    {
        return ! $this->sensitive || Person::canViewMedical($user);
    }

    /** @return Collection<int, ExtraField> active fields of the type that the user may see, in form order */
    public static function shownTo(PersonType $type, ?User $user): Collection
    {
        return static::active()->forType($type)->orderBy('id')->get()->filter(fn (ExtraField $f) => $f->visibleTo($user))->values();
    }

    /** @return Collection<int, ExtraField> active "required to be complete" fields of the type (memoised; flushed on save) */
    public static function requiredFor(PersonType $type): Collection
    {
        return once(fn () => static::active()->forType($type)->where('required', true)->orderBy('id')->get());
    }

    /** Raw input (form, Excel cell) to the stored text; null = blank. */
    public function normalise(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($this->type) {
            ExtraFieldType::YesNo => match (strtolower($value)) {
                'yes', 'y', '1', 'true' => '1',
                'no', 'n', '0', 'false' => '0',
                default => $value,
            },
            // Excel stores dates as day serials from 1899-12-30.
            ExtraFieldType::Date => is_numeric($value)
                ? (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d') : $value,
            default => $value,
        };
    }

    /** Laravel rules for a normalised value. */
    public function rules(): array
    {
        return ['nullable', ...match ($this->type) {
            ExtraFieldType::Text => ['string', 'max:255'],
            ExtraFieldType::Number => ['numeric'],
            ExtraFieldType::Date => ['date_format:Y-m-d'],
            ExtraFieldType::Choice => [Rule::in($this->options ?? [])],
            ExtraFieldType::YesNo => [Rule::in(['0', '1'])],
        }];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
