<?php

namespace App\Jobs;

use App\Enums\PersonType;
use App\Enums\StaPermission;
use App\Models\Association;
use App\Models\ExportLayout;
use App\Models\Person;
use App\Models\User;
use App\Services\GameFiles\GameFileExporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/** Spec 15.3: photos and optional documents of the included people, named by the layout's pattern, on the private disk. */
class BuildGameFilesZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // One long attempt: a retry while a killed run is still writing would build the ZIP twice in parallel.
    // The worker needs --timeout >= 600 and the queue's retry_after above it (README).
    public $timeout = 600;

    public $tries = 1;

    /** Local working dir, fixed at dispatch so failed() can remove it (the ZIP is "{$dir}.zip"). */
    public string $dir;

    public const KINDS = ['photo_path' => 'photo', 'passport_scan_path' => 'passport', 'cpr_front_path' => 'cpr_front',
        'wada_certificate_path' => 'wada'];

    /** @param list<string> $columns optional document columns; the photo is always included */
    public function __construct(
        public ExportLayout $layout,
        public User $user,
        public array $columns = [],
        public ?int $associationId = null,
        public ?int $gameSportId = null,
    ) {
        $this->dir = sys_get_temp_dir().'/zip-'.Str::uuid();
    }

    public function failed(?Throwable $e): void
    {
        File::deleteDirectory($this->dir);
        File::delete("{$this->dir}.zip");
        Notification::make()->danger()->title('Files ZIP not built')
            ->body('Something went wrong while building the ZIP. Please try again.')->sendToDatabase($this->user);
    }

    public function handle(GameFileExporter $exporter): void
    {
        $game = $this->layout->game;
        // Re-checked at run time: the game may have gone back to draft, or the requester lost the permission, since the click.
        $refusal = match (true) {
            ! $game->isPublished() => "{$game->name} is a draft: draft games have no game system files.",
            ! $this->user->can(StaPermission::ExportGameFiles->value) => 'You no longer have permission to export game system files.',
            default => null,
        };
        if ($refusal) {
            Notification::make()->danger()->title('Files ZIP not built')->body($refusal)->sendToDatabase($this->user);

            return;
        }

        $disk = Storage::disk(config('filesystems.private'));
        $columns = array_values(array_intersect(array_keys(self::KINDS), ['photo_path', ...$this->columns]));
        $missing = [];
        if (in_array('wada_certificate_path', $columns, true) && ! Person::canViewMedical($this->user)) {
            $columns = array_values(array_diff($columns, ['wada_certificate_path']));
            $missing[] = 'WADA certificates left out: they need "View medical & health data".';
        }
        $people = $exporter->people($game, $this->layout->type, $this->associationId, $this->gameSportId);

        // Files are copied to a local temp dir first: ZipArchive::addFile reads them only on close(), and the disk may be S3.
        $dir = $this->dir;
        $tmp = "{$dir}.zip";
        File::ensureDirectoryExists($dir);
        $who = $this->layout->type === PersonType::Athlete ? 'athletes' : 'officials';
        $path = 'exports/'.Str::uuid().'/'.Str::slug($game->name)."-{$who}-files.zip";
        $count = 0;
        try {
            $zip = new ZipArchive;
            // Every ZipArchive call is checked: a corrupt or empty ZIP must never be offered as ready.
            $ok = fn (bool $done, string $what) => $done ?: throw new RuntimeException("ZIP {$what} failed.");
            $ok($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'open');
            $used = [];
            foreach ($people as ['person' => $p, 'association' => $a]) {
                foreach ($columns as $column) {
                    $kind = self::KINDS[$column];
                    $file = $p->{$column};
                    if (blank($file) || ! $disk->exists($file)) {
                        $missing[] = "{$p->code} ".($p->full_name ?: $p->fullName()).": {$kind}";

                        continue;
                    }
                    $local = $dir.'/'.(++$count);
                    $in = $disk->readStream($file);
                    $out = fopen($local, 'w');
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    fclose($out);
                    $ok($zip->addFile($local, self::fileName($this->layout->zip_pattern, $p, $a, $kind, pathinfo($file, PATHINFO_EXTENSION), $used)), 'addFile');
                }
            }
            if ($missing || $count === 0) {
                $ok($zip->addFromString('missing.txt', implode("\n", $missing ?: ['No files.'])."\n"), 'addFromString');
            }
            $ok($zip->close(), 'close');

            $stream = fopen($tmp, 'r');
            $disk->put($path, $stream);
            fclose($stream);
        } finally {
            File::deleteDirectory($dir);
            File::delete($tmp);
        }
        // ponytail: ZIPs stay under exports/ until removed; add a scheduled prune of exports/ older than a day if storage grows.

        // The pattern is recorded so file names built from passport numbers are traceable; never file contents.
        activity()->performedOn($game)->causedBy($this->user)->event('exported')
            ->withProperties(['layout' => $this->layout->type->value, 'pattern' => $this->layout->zip_pattern, 'people' => $people->count(),
                'files' => $count, 'missing' => count($missing), 'documents' => $columns, 'association_id' => $this->associationId,
                'game_sport_id' => $this->gameSportId])
            ->log('game files ZIP exported');

        Notification::make()->success()->title('Files ZIP ready')
            ->body("{$game->name}, {$who}: {$count} files. The link works for 5 minutes.")
            ->actions([Action::make('download')->label('Download ZIP')
                ->url($disk->temporaryUrl($path, now()->addMinutes(5)), shouldOpenInNewTab: true)])
            ->sendToDatabase($this->user);
    }

    /**
     * Zip-slip safe: path separators, Windows-reserved and control characters become "_", bidi controls and leading/trailing
     * dots and spaces go, reserved device names (CON, COM1…) get "_" appended, and the name is capped at 200 characters.
     *
     * @param  array<string, true>  $used  lower-cased names already in the ZIP (duplicates get -2, -3, …)
     */
    public static function fileName(string $pattern, Person $p, Association $a, string $kind, string $ext, array &$used): string
    {
        $base = strtr($pattern, ['{code}' => (string) $p->code, '{passport_no}' => (string) $p->passport_no, '{last_name}' => (string) $p->last_name,
            '{first_name}' => (string) $p->first_name, '{association_code}' => (string) $a->code, '{kind}' => $kind]);
        $base = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $base); // bidi controls could disguise the extension
        $base = trim(mb_substr(trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F\x7F]/u', '_', $base), ' .'), 0, 200), ' .') ?: 'file';
        $base = preg_replace('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?=\.|$)/i', '$1_', $base); // Windows reserved device names
        $ext = preg_replace('/[^a-z0-9]/', '', strtolower($ext)) ?: 'bin';
        $name = "{$base}.{$ext}";
        for ($n = 2; isset($used[mb_strtolower($name)]); $n++) {
            $name = "{$base}-{$n}.{$ext}";
        }
        $used[mb_strtolower($name)] = true;

        return $name;
    }
}
