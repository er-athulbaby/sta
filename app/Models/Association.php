<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Association extends Model implements HasAvatar
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'code', 'email', 'logo_path', 'phone', 'address', 'website', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function code(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => $v === null ? null : strtoupper(trim($v)));
    }

    /** Tenant menu logo (spec 17: the portal menu shows the association's logo); without one, InitialsAvatarProvider draws the code. */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo_path ? Storage::disk(config('filesystems.private'))->temporaryUrl($this->logo_path, now()->addMinutes(5)) : null;
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function sports(): HasMany
    {
        return $this->hasMany(Sport::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
