<?php

namespace App\Models;

use App\Enums\PhaseStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Game extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'logo_path', 'start_date', 'end_date', 'reminder_days'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'reminder_days' => 'array'];
    }

    protected static function booted(): void
    {
        static::created(function (Game $game) {
            foreach ([1, 2, 3, 4] as $n) {
                $game->phases()->create(['phase' => $n, 'enabled' => false, 'status' => PhaseStatus::Draft]);
            }
        });
    }

    public function associations(): BelongsToMany
    {
        return $this->belongsToMany(Association::class, 'game_associations');
    }

    public function clothingItems(): BelongsToMany
    {
        return $this->belongsToMany(ClothingItem::class, 'game_clothing_items');
    }

    public function gameSports(): HasMany
    {
        return $this->hasMany(GameSport::class);
    }

    public function gameEvents(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(GamePhase::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function phase(int $n): GamePhase
    {
        return $this->phases->firstWhere('phase', $n) ?? $this->phases()->where('phase', $n)->firstOrFail();
    }

    public function isInvited(Association $a): bool
    {
        return $this->associations()->whereKey($a->getKey())->exists();
    }

    /** @return Collection<int, GameSport> */
    public function sportsFor(Association $a): Collection
    {
        return $this->gameSports()->whereHas('sport', fn ($q) => $q->where('association_id', $a->getKey()))
            ->with(['sport', 'gameEvents.event'])->get()->sortBy('sport.name')->values();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
