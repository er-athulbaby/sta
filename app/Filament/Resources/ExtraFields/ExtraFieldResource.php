<?php

namespace App\Filament\Resources\ExtraFields;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Filament\Resources\ExtraFields\Pages\CreateExtraField;
use App\Filament\Resources\ExtraFields\Pages\EditExtraField;
use App\Filament\Resources\ExtraFields\Pages\ListExtraFields;
use App\Filament\Resources\ExtraFields\Schemas\ExtraFieldForm;
use App\Filament\Resources\ExtraFields\Tables\ExtraFieldsTable;
use App\Models\ExtraField;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExtraFieldResource extends Resource
{
    protected static ?int $navigationSort = 10;

    use RequiresStaPermission;

    protected static ?string $model = ExtraField::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageMasterData;
    }

    public static function form(Schema $schema): Schema
    {
        return ExtraFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExtraFieldsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtraFields::route('/'),
            'create' => CreateExtraField::route('/create'),
            'edit' => EditExtraField::route('/{record}/edit'),
        ];
    }
}
