<?php

namespace App\Filament\Shared;

use App\Services\Mrz\MrzParser;
use App\Services\Mrz\MrzReader;
use App\Services\Mrz\PassportScanFile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use InvalidArgumentException;

final class ReadPassportAction
{
    public static function make(): Action
    {
        return Action::make('readPassport')
            ->label('Read details from passport')
            ->icon('heroicon-o-identification')
            ->visible(fn (Get $get) => filled($get('passport_scan_path')))
            ->action(function (Get $get, Set $set) {
                $state = $get('passport_scan_path');
                $path = PassportScanFile::localPath($state);
                try {
                    $lines = $path ? app(MrzReader::class)->read($path) : null;
                } finally {
                    PassportScanFile::release($state, $path);
                }
                try {
                    $r = $lines ? (new MrzParser)->parse($lines[0], $lines[1]) : null;
                } catch (InvalidArgumentException) {
                    $r = null;
                }
                if ($r === null) {
                    Notification::make()->danger()->title("Couldn't read the passport. Try a clearer, flat scan.")->send();

                    return;
                }
                $country = fn (?string $code) => $code ? (config('countries')[$code] ?? $code) : null;
                [$first, $second] = [$r->givenNames[0] ?? null, $r->givenNames[1] ?? null];
                $third = count($r->givenNames) > 2 ? implode(' ', array_slice($r->givenNames, 2)) : null;

                $values = ['full_name' => $r->fullName, 'first_name' => $first, 'second_name' => $second, 'third_name' => $third,
                    'last_name' => $r->surname, 'passport_no' => $r->passportNo, 'passport_issuing_country' => $country($r->issuingCode),
                    'nationality' => $country($r->nationalityCode), 'dob' => $r->dob, 'gender' => $r->gender, 'passport_expiry' => $r->expiry];
                foreach ($values as $field => $value) {
                    if ($value !== null) {
                        $set($field, $value);
                    }
                }

                $note = Notification::make()->title('Passport read — please check the details before saving');
                $r->invalid ? $note->warning()->body('Could not confirm: '.implode(', ', $r->invalid).'. Enter these by hand.') : $note->success();
                $note->send();
            });
    }
}
