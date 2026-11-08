<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Sport extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['association_id', 'name', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function association(): BelongsTo { return $this->belongsTo(Association::class); }

    public function events(): HasMany { return $this->hasMany(Event::class); }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
