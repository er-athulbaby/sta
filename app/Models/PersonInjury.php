<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonInjury extends Model
{
    public const STATUSES = ['Recovered', 'In treatment', 'Ongoing'];

    protected $fillable = ['person_id', 'injury_date', 'description', 'status'];

    protected function casts(): array
    {
        return ['injury_date' => 'date', 'description' => 'encrypted'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
