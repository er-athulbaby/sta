<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonSize extends Model
{
    protected $fillable = ['person_id', 'clothing_item_id', 'size'];

    public function clothingItem(): BelongsTo
    {
        return $this->belongsTo(ClothingItem::class);
    }
}
