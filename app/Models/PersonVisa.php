<?php

namespace App\Models;

use App\Models\Concerns\RenamesAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonVisa extends Model
{
    use RenamesAttachments;

    public const STATUSES = ['Not started', 'Applied', 'Approved', 'Rejected', 'Visa on arrival', 'e-Visa'];

    protected $fillable = ['person_id', 'country', 'status', 'visa_no', 'valid_until', 'copy_path'];

    protected function casts(): array
    {
        return ['valid_until' => 'date'];
    }

    protected function attachmentColumns(): array
    {
        return ['copy_path' => 'visa_'.$this->id];
    }

    protected function attachmentPrefix(): string
    {
        return $this->person->code;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
