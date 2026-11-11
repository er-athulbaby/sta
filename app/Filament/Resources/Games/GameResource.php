<?php

namespace App\Filament\Resources\Games;

use App\Enums\StaPermission;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\GameExportFiles;
use App\Filament\Resources\Games\Pages\GameReports;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\Pages\SetupLimits;
use App\Filament\Resources\Games\Pages\SetupPhases;
use App\Filament\Resources\Games\Pages\SetupShortList;
use App\Filament\Resources\Games\Pages\StaPhase1Edit;
use App\Filament\Resources\Games\Pages\StaPhase2Edit;
use App\Filament\Resources\Games\Pages\StaPhase3Edit;
use App\Filament\Resources\Games\Pages\StaPhase4Edit;
use App\Filament\Resources\Games\Pages\SubmissionView;
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
    protected static ?int $navigationSort = 1;

    protected static ?string $model = Game::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(StaPermission::ManageGames->value) || $u?->can(StaPermission::ViewSubmissions->value)
            || $u?->can(StaPermission::ViewReports->value) || $u?->can(StaPermission::ExportGameFiles->value));
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::manageResponse();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::manageResponse();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::manageResponse();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
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
        // The four setup screens are custom pages; the relation managers are no longer shown.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGames::route('/'),
            'create' => CreateGame::route('/create'),
            'edit' => EditGame::route('/{record}/edit'),
            'setup-limits' => SetupLimits::route('/{record}/setup/limits'),
            'setup-short-list' => SetupShortList::route('/{record}/setup/short-list'),
            'setup-phases' => SetupPhases::route('/{record}/setup/phases'),
            'dashboard' => GameDashboard::route('/{record}/dashboard'),
            'reports' => GameReports::route('/{record}/reports'),
            'export-files' => GameExportFiles::route('/{record}/export-files'),
            'submission' => SubmissionView::route('/{record}/submissions/{submission}'),
            'submission-edit-1' => StaPhase1Edit::route('/{record}/submissions/{submission}/edit-phase-1'),
            'submission-edit-2' => StaPhase2Edit::route('/{record}/submissions/{submission}/edit-phase-2'),
            'submission-edit-3' => StaPhase3Edit::route('/{record}/submissions/{submission}/edit-phase-3'),
            'submission-edit-4' => StaPhase4Edit::route('/{record}/submissions/{submission}/edit-phase-4'),
        ];
    }
}
