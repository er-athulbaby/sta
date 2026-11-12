<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?int $navigationSort = 7;

    use RequiresStaPermission;

    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'activity-logs';

    protected static ?string $navigationLabel = 'Audit log';

    private const PILL = 'display: inline-flex; align-items: center; gap: 6px; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap';

    private const MEDICAL_PILL = '<span style="'.self::PILL.'; background: var(--sta-bg-subtle, #F1F5F9); color: var(--sta-fg-strong, #334155)"><svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>Medical data changed</span>';

    private const DOCUMENTS_PILL = '<span style="'.self::PILL.'; background: var(--sta-bg-indigo-100, #E0E7FF); color: var(--sta-fg-blue-900, #1E3A8A)">Documents changed</span>';

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ViewAuditLog;
    }

    public static function canCreate(): bool
    {
        return false;
    }

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
                // The canvas "Recent activity" marks (AdminDashboard.dc.html); values are never shown.
                TextColumn::make('marks')->label('Marked')->html()
                    ->state(fn (Activity $record) => new HtmlString(implode(' ', array_filter([
                        $record->properties->get('medical_changed') ? self::MEDICAL_PILL : null,
                        $record->properties->get('documents_changed') ? self::DOCUMENTS_PILL : null,
                    ])))),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Record type')->options(fn () => Activity::query()->distinct()->pluck('subject_type')
                    ->filter()->mapWithKeys(fn ($t) => [$t => class_basename($t)])->all()),
            ])
            ->recordActions([
                Action::make('details')->modalSubmitAction(false)
                    ->modalContent(fn (Activity $record) => new HtmlString(
                        '<pre style="white-space:pre-wrap;font-size:12px">'.e(json_encode($record->properties, JSON_PRETTY_PRINT)).'</pre>'
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListActivityLogs::route('/')];
    }
}
