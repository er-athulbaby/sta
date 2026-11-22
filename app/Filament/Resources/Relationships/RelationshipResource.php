<?php

namespace App\Filament\Resources\Relationships;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Filament\Resources\Relationships\Pages\CreateRelationship;
use App\Filament\Resources\Relationships\Pages\EditRelationship;
use App\Filament\Resources\Relationships\Pages\ListRelationships;
use App\Models\Relationship;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Master data like Clothing items: the names offered for emergency contact and guardian relationship. Deactivated, never deleted. */
class RelationshipResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = Relationship::class;

    protected static ?string $navigationLabel = 'Relationships';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageMasterData;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(30)->unique(ignoreRecord: true)->helperText('e.g. Father, Mother, Guardian'),
            TextInput::make('sort_order')->label('Sort order')->required()->integer()->minValue(0)->default(0)->helperText('Lower numbers come first in the dropdown'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        // No delete actions: relationships are only deactivated, so saved person values keep their meaning.
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('sort_order')->label('Sort order')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelationships::route('/'),
            'create' => CreateRelationship::route('/create'),
            'edit' => EditRelationship::route('/{record}/edit'),
        ];
    }
}
