<?php

namespace App\Filament\Resources\Games;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\RelationManagers\GameSportsRelationManager;
use App\Filament\Resources\Games\RelationManagers\PhasesRelationManager;
use App\Filament\Resources\Games\Schemas\GameForm;
use App\Filament\Resources\Games\Tables\GamesTable;
use App\Models\Game;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class GameResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = Game::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Games';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageGames;
    }

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(StaPermission::ManageGames->value) || $u?->can(StaPermission::ViewSubmissions->value));
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::manageResponse();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::manageResponse();
    }

    private static function manageResponse(): Response
    {
        return Gate::inspect(StaPermission::ManageGames->value);
    }

    public static function form(Schema $schema): Schema
    {
        return GameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GamesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [PhasesRelationManager::class, GameSportsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGames::route('/'),
            'create' => CreateGame::route('/create'),
            'edit' => EditGame::route('/{record}/edit'),
            'dashboard' => GameDashboard::route('/{record}/dashboard'),
        ];
    }
}
