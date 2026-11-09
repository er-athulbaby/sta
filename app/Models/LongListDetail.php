<?php

namespace App\Models;

use App\Models\Concerns\RenamesAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/** Per game, per person travel and insurance details of a Long List (spec 13 "Per game"). */
class LongListDetail extends Model
{
    use LogsActivity, RenamesAttachments;

    public const MEDICAL = ['insurance_policy_no', 'vaccinations'];

    protected $fillable = ['submission_id', 'person_id', 'insurance_company', 'insurance_policy_no', 'insurance_valid_until',
        'insurance_certificate_path', 'vaccinations'];

    protected function casts(): array
    {
        return ['insurance_policy_no' => 'encrypted', 'insurance_valid_until' => 'date'];
    }

    protected static function booted(): void
    {
        static::deleted(function (LongListDetail $d) {
            if ($d->insurance_certificate_path) {
                Storage::disk(config('filesystems.private'))->delete($d->insurance_certificate_path);
            }
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected function attachmentColumns(): array
    {
        return ['insurance_certificate_path' => 'insurance'];
    }

    protected function attachmentPrefix(): string
    {
        return $this->person->code;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('submission-'.$this->submission_id)
            ->logFillable()->logExcept(self::MEDICAL)->logOnlyDirty();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($eventName === 'updated' && collect(self::MEDICAL)->contains(fn ($c) => $this->wasChanged($c))) {
            $activity->properties = $activity->properties->put('medical_changed', true);
        }
    }
}
