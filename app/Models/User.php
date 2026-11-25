<?php

namespace App\Models;

use App\Enums\Role;
use App\Filament\Shared\AttachmentActions;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAvatar, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, LogsActivity, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'association_id', 'is_active', 'photo_path', 'phone_cc', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['phone_cc' => '+973'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    /** The user group of an STA user (spatie role); every STA user has exactly one. */
    public function group(): ?StaGroup
    {
        return $this->roles->first();
    }

    /** Sidebar and list avatar: the photo through a 5-minute signed link, or null for the initials circle. */
    public function getFilamentAvatarUrl(): ?string
    {
        return filled($this->photo_path) ? AttachmentActions::url($this->photo_path) : null;
    }

    /** "+973 3300 0020", or null without a number. */
    public function mobile(): ?string
    {
        return filled($this->phone) ? $this->phone_cc.' '.$this->phone : null;
    }

    /** Lock-out guard: the last active Super Admin can be neither demoted nor deactivated. */
    protected static function booted(): void
    {
        static::updating(function (User $user) {
            $wasActiveSuper = $user->getOriginal('role') === Role::SuperAdmin && $user->getOriginal('is_active');
            if (! $wasActiveSuper || ($user->isSuperAdmin() && $user->is_active)) {
                return;
            }
            $others = static::withoutGlobalScopes()->whereKeyNot($user->getKey())->where('role', Role::SuperAdmin)->where('is_active', true)->exists();
            if (! $others) {
                throw ValidationException::withMessages(['role' => 'This is the last active Super Admin: it cannot be demoted or deactivated.']);
            }
        });
        // A replaced or removed photo leaves no file behind.
        static::updated(function (User $user) {
            if ($user->wasChanged('photo_path') && filled($old = $user->getOriginal('photo_path'))) {
                Storage::disk(config('filesystems.private'))->delete($old);
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->role === Role::SuperAdmin || $this->role === Role::Sta,
            'portal' => $this->role->isAssociationRole() && (bool) $this->association?->is_active,
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        return collect([$this->association])->filter();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Association && $tenant->getKey() === $this->association_id;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'role', 'association_id', 'is_active', 'photo_path', 'phone_cc', 'phone'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
