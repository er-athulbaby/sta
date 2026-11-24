<?php

namespace App\Filament\Resources\StaGroups;

use App\Enums\StaArea;
use App\Filament\Concerns\RequiresStaPermission;
use App\Models\StaGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** "Users & groups" > Groups: a name, a description and the permissions grid (Users & groups: Access / New / Save / Delete). */
class StaGroupResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = StaGroup::class;

    protected static ?string $slug = 'user-groups';

    protected static ?string $modelLabel = 'user group';

    protected static bool $shouldRegisterNavigation = false; // reached through the "Users & groups" tabs

    protected static function staArea(): StaArea
    {
        return StaArea::Users;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120)->unique(ignoreRecord: true),
            Textarea::make('description')->maxLength(500)->rows(2),
            ViewField::make('cells')->label('Permissions')->view('filament.resources.sta-groups.permission-grid')
                ->default([])->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['users', 'permissions']))
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('description')->wrap()->color('gray'),
                TextColumn::make('users_count')->label('Users'),
                TextColumn::make('permissions_count')->label('Ticked cells'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), self::deleteAction()]);
    }

    /** A group still holding users cannot be deleted (every STA user has exactly one group). */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()->before(function (StaGroup $record, DeleteAction $action) {
            if ($record->users()->exists()) {
                Notification::make()->danger()->title('Move this group\'s users to another group first.')->send();
                $action->cancel();
            }
        }); // the deletion itself is audited by LogsActivity
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaGroups::route('/'),
            'create' => Pages\CreateStaGroup::route('/create'),
            'edit' => Pages\EditStaGroup::route('/{record}/edit'),
        ];
    }
}
