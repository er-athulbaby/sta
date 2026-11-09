<?php

namespace App\Filament\Shared;

use App\Models\Person;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Storage;

final class AttachmentActions
{
    public const LABELS = ['photo_path' => 'Photo', 'passport_scan_path' => 'Passport', 'cpr_front_path' => 'CPR front',
        'cpr_back_path' => 'CPR back', 'cpr_reader_path' => 'CPR reader copy', 'wada_certificate_path' => 'WADA certificate'];

    public static function group(): ActionGroup
    {
        return ActionGroup::make(collect(self::LABELS)->map(fn (string $label, string $column) => Action::make('view_'.$column)
            ->label('View '.$label)->icon('heroicon-o-eye')
            ->visible(fn (Person $record) => filled($record->{$column}))
            ->url(fn (Person $record) => self::url($record->{$column}), shouldOpenInNewTab: true))->values()->all())
            ->label('Attachments')->icon('heroicon-o-paper-clip');
    }

    public static function url(string $path): string
    {
        return Storage::disk(config('filesystems.private'))->temporaryUrl($path, now()->addMinutes(5));
    }
}
