<?php

namespace App\Models;

use App\Enums\AppliesTo;
use App\Enums\PersonType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClothingItem extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sizes', 'applies_to', 'is_active'];

    protected function casts(): array
    {
        return ['sizes' => 'array', 'applies_to' => AppliesTo::class, 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, PersonType $type): void
    {
        $query->whereIn('applies_to', [AppliesTo::Both->value, $type->value]);
    }

    public function hasSize(string $size): bool
    {
        return in_array($size, $this->sizes, true);
    }
}
