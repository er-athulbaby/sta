<?php

namespace App\Models;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\Concerns\BelongsToAssociation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Person extends Model
{
    use BelongsToAssociation, HasFactory;

    public const FILE_COLUMNS = ['photo_path', 'passport_scan_path', 'cpr_scan_path'];
    public const OFFICIAL_ONLY = ['function', 'phone', 'email'];

    protected $fillable = [
        'association_id', 'type', 'first_name', 'second_name', 'third_name', 'last_name', 'photo_path',
        'passport_no', 'passport_expiry', 'passport_scan_path', 'cpr_no', 'cpr_expiry', 'cpr_scan_path',
        'nationality', 'dob', 'gender', 'height_cm', 'weight_kg', 'function', 'phone', 'email', 'is_active',
    ];

    protected $hidden = ['passport_no_hash', 'cpr_no_hash'];

    protected function casts(): array
    {
        return [
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
            if ($person->type === PersonType::Athlete) {
                foreach (self::OFFICIAL_ONLY as $column) {
                    $person->{$column} = null;
                }
            }
        });

        static::deleted(function (Person $person) {
            $paths = array_filter(array_map(fn ($c) => $person->{$c}, self::FILE_COLUMNS));
            Storage::disk(config('filesystems.private'))->delete($paths);
        });
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
}
