<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonVisa extends Model
{
    public const STATUSES = ['Not started', 'Applied', 'Approved', 'Rejected', 'Visa on arrival', 'e-Visa'];

    protected $fillable = ['person_id', 'country', 'status', 'visa_no', 'valid_until', 'copy_path'];

    protected function casts(): array
    {
        return ['valid_until' => 'date'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
