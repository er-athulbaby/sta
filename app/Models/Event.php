<?php

namespace App\Models;

use App\Enums\EventGender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['sport_id', 'name', 'gender', 'is_active'];

    protected function casts(): array { return ['gender' => EventGender::class, 'is_active' => 'boolean']; }

    public function sport(): BelongsTo { return $this->belongsTo(Sport::class); }
}
