<?php

namespace App\Filament\Shared;

use App\Services\Mrz\MrzParser;
use App\Services\Mrz\MrzReader;
use App\Services\Mrz\PassportScanFile;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Livewire\Component;
use Throwable;

/**
 * Canvas "Read details from passport" (PlayerForm / OfficialForm): fills the form from the scan's MRZ and reports the result
 * in the inline status box under the button (the page's $passportRead, drawn by filament.people.passport-read).
 */
final class ReadPassportAction
{
    private const LABELS = ['full_name' => 'full name', 'first_name' => 'first name', 'second_name' => 'second name', 'third_name' => 'third name',
        'last_name' => 'last name', 'passport_no' => 'passport number', 'passport_issuing_country' => 'issuing country', 'nationality' => 'nationality',
        'dob' => 'date of birth', 'gender' => 'gender', 'passport_expiry' => 'expiry date'];

    public const UNREADABLE = "Couldn't read the passport. Try a clearer, flat scan.";

    public static function make(): Action
    {
        return Action::make('readPassport')
            ->label('Read details from passport')
            ->icon(new HtmlString('<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V5a1 1 0 0 1 1-1h3"/><path d="M16 4h3a1 1 0 0 1 1 1v3"/><path d="M20 16v3a1 1 0 0 1-1 1h-3"/><path d="M8 20H5a1 1 0 0 1-1-1v-3"/><path d="M7 10h10"/><path d="M7 14h10"/></svg>'))
            ->button()->extraAttributes(['class' => 'sta-read-passport'])
            ->visible(fn (Get $get) => filled($get('passport_scan_path')))
            ->action(function (FileUpload $component, Get $get, Set $set, Component $livewire) {
                $state = $get('passport_scan_path');
                // A stored string path is client-controlled in Livewire state: only the record's own files may be read.
                $item = is_array($state) ? (array_values($state)[0] ?? null) : $state;
                if (is_string($item) && ! $component->isFilePathAuthorized($item)) {
                    $state = null;
                }
                $path = PassportScanFile::localPath($state);
                try {
                    $lines = $path ? app(MrzReader::class)->read($path) : null;
                } catch (Throwable $e) {
                    report($e); // missing binary / process failure: show the normal "couldn't read" notice
                    $lines = null;
                } finally {
                    PassportScanFile::release($state, $path);
                }
                try {
                    $r = $lines ? (new MrzParser)->parse($lines[0], $lines[1]) : null;
                } catch (InvalidArgumentException) {
                    $r = null;
                }
                if ($r === null) {
                    $livewire->passportRead = ['tone' => 'error', 'text' => self::UNREADABLE];

                    return;
                }
                $country = fn (?string $code) => $code ? (config('countries')[$code] ?? $code) : null;
                [$first, $second] = [$r->givenNames[0] ?? null, $r->givenNames[1] ?? null];
                $third = count($r->givenNames) > 2 ? implode(' ', array_slice($r->givenNames, 2)) : null;

                $values = ['full_name' => $r->fullName, 'first_name' => $first, 'second_name' => $second, 'third_name' => $third,
                    'last_name' => $r->surname, 'passport_no' => $r->passportNo, 'passport_issuing_country' => $country($r->issuingCode),
                    'nationality' => $country($r->nationalityCode), 'dob' => $r->dob, 'gender' => $r->gender, 'passport_expiry' => $r->expiry];
                $filled = [];
                foreach ($values as $field => $value) {
                    if ($value !== null) {
                        $set($field, $value);
                        $filled[] = self::LABELS[$field];
                    }
                }

                $text = 'Filled '.count($filled).' fields from the passport: '.implode(', ', $filled).'. Please check them, then save.';
                $livewire->passportRead = $r->invalid
                    ? ['tone' => 'warn', 'text' => $text.' Could not confirm: '.implode(', ', $r->invalid).'. Enter these by hand.']
                    : ['tone' => 'ok', 'text' => $text];
            });
    }
}
