<?php

namespace App\Models;

use App\Models\Concerns\RenamesAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PersonVisa extends Model
{
    use RenamesAttachments;

    public const STATUSES = ['Not started', 'Applied', 'Approved', 'Rejected', 'Visa on arrival', 'e-Visa'];

    protected $fillable = ['person_id', 'country', 'status', 'visa_no', 'valid_until', 'copy_path'];

    protected function casts(): array
    {
        return ['valid_until' => 'date'];
    }

    protected static function booted(): void
    {
        // A removed row would otherwise orphan its copy. (Person deletion cascades in the DB and cleans up itself.)
        static::deleted(function (PersonVisa $visa) {
            if (filled($visa->copy_path)) {
                Storage::disk(config('filesystems.private'))->delete($visa->copy_path);
            }
        });
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
