<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\PersonType;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Sql;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a person's size for one clothing item. With a game: listed people and that game's items only. */
final class ClothingSizes extends Subject
{
    public function key(): string
    {
        return 'clothing_sizes';
    }

    public function label(): string
    {
        return 'Clothing sizes';
    }

    public function gameBased(): bool
    {
        return false;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('person_sizes')
            ->join('people', 'people.id', '=', 'person_sizes.person_id')
            ->join('associations', 'associations.id', '=', 'people.association_id')
            ->join('clothing_items', 'clothing_items.id', '=', 'person_sizes.clothing_item_id')
            ->when($gameId, fn (Builder $q) => $q
                ->whereIn('person_sizes.clothing_item_id', fn (Builder $s) => $s->select('clothing_item_id')->from('game_clothing_items')->where('game_id', $gameId))
                ->whereIn('people.id', People::onLists($gameId)));
    }

    public function defaultColumns(): array
    {
        return ['full_name', 'association', 'item', 'size'];
    }

    protected function define(): array
    {
        return [
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('code', 'Person code', 'Person', 'people.code'),
            Field::text('full_name', 'Name', 'Person', Sql::fullName()),
            Field::enum('type', 'Type', 'Person', 'people.type', Field::labels(PersonType::class)),
            Field::text('item', 'Item', 'Size', 'clothing_items.name'),
            Field::text('size', 'Size', 'Size', 'person_sizes.size'),
        ];
    }
}
