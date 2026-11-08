<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    use RequiresStaPermission;

    protected static ?string $model = Activity::class;
    protected static ?string $slug = 'activity-logs';
    protected static ?string $navigationLabel = 'Audit log';
    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ViewAuditLog;
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y H:i')->label('When'),
                TextColumn::make('causer.name')->label('Who')->default('System'),
                TextColumn::make('event')->badge(),
                TextColumn::make('subject_type')->label('Record')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
                TextColumn::make('subject_id')->label('ID'),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Record type')->options(fn () => Activity::query()->distinct()->pluck('subject_type')
                    ->filter()->mapWithKeys(fn ($t) => [$t => class_basename($t)])->all()),
            ])
            ->recordActions([
                Action::make('details')->modalSubmitAction(false)
                    ->modalContent(fn (Activity $record) => new \Illuminate\Support\HtmlString(
                        '<pre style="white-space:pre-wrap;font-size:12px">'.e(json_encode($record->properties, JSON_PRETTY_PRINT)).'</pre>'
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListActivityLogs::route('/')];
    }
}
