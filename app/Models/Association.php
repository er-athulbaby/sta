<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Association extends Model
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
