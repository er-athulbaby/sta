<?php

namespace App\Filament\Resources\People;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Filament\Shared\PersonForm;
use App\Filament\Shared\PersonTable;
use App\Models\Person;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PersonResource extends Resource
{
    protected static ?int $navigationSort = 5;

    use RequiresStaPermission;

    protected static ?string $model = Person::class;

    protected static ?string $slug = 'people';

    protected static ?string $navigationLabel = 'People';

    protected static ?string $modelLabel = 'person';

    protected static ?string $pluralModelLabel = 'people';

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManagePeople;
    }

    public static function form(Schema $schema): Schema
    {
        return PersonForm::configure($schema, withAssociation: true);
    }

    public static function table(Table $table): Table
    {
        return PersonTable::configure($table, withAssociation: true);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPeople::route('/'),
            'create' => Pages\CreatePerson::route('/create'),
            'edit' => Pages\EditPerson::route('/{record}/edit'),
        ];
    }
}
