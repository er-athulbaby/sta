<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One extra-field value of a person (spec 15.1). Written only through Person::syncExtraValues(), which audits. */
class PersonFieldValue extends Model
{
    protected $fillable = ['person_id', 'extra_field_id', 'value'];

    public function extraField(): BelongsTo
    {
        return $this->belongsTo(ExtraField::class);
    }
}
