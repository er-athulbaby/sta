<?php

namespace Database\Seeders;

use App\Enums\AppliesTo;
use App\Models\ClothingItem;
use Illuminate\Database\Seeder;

class ClothingItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'T-Shirt' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'],
            'Suit' => ['44', '46', '48', '50', '52', '54', '56', '58'],
            'Shoes' => array_map('strval', range(35, 48)),
        ];
        foreach ($items as $name => $sizes) {
            ClothingItem::firstOrCreate(['name' => $name], ['sizes' => $sizes, 'applies_to' => AppliesTo::Both, 'is_active' => true]);
        }
    }
}
