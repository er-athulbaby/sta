<?php

namespace App\Filament\Resources\Associations;

use App\Filament\Resources\Associations\Pages\CreateAssociation;
use App\Filament\Resources\Associations\Pages\EditAssociation;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Filament\Resources\Associations\Schemas\AssociationForm;
use App\Filament\Resources\Associations\Tables\AssociationsTable;
use App\Filament\Resources\Associations\RelationManagers;
use App\Models\Association;
use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AssociationResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = Association::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageAssociations;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AssociationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssociationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
            RelationManagers\InvitationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssociations::route('/'),
            'create' => CreateAssociation::route('/create'),
            'edit' => EditAssociation::route('/{record}/edit'),
        ];
    }
}
