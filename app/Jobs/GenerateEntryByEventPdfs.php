<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateEntryByEventPdfs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    public function __construct(public Submission $submission) {}

    public function handle(): void
    {
        $s = $this->submission->fresh(['game', 'association']);
        $disk = Storage::disk(config('filesystems.private'));
        $paths = [];
        $counts = $s->numbers()->pluck('count', 'game_event_id');
        foreach ($s->game->phase2SportsFor($s->association) as $gs) {
            $order = ['men' => 0, 'women' => 1, 'mixed' => 2];
            $rows = $gs->gameEvents->map(fn ($ge) => [
                'event' => $ge->event->name, 'gender' => $ge->event->gender->value, 'count' => (int) ($counts[$ge->id] ?? 0),
            ])->sortBy([fn ($a, $b) => ($order[$a['gender']] ?? 3) <=> ($order[$b['gender']] ?? 3), ['event', 'asc']])->values()->all();
            $totals = ['men' => collect($rows)->where('gender', 'men')->sum('count'), 'women' => collect($rows)->where('gender', 'women')->sum('count'),
                'total' => collect($rows)->sum('count')];
            $html = view('pdf.entry-by-event', [
                'game' => $s->game, 'association' => $s->association, 'gameSport' => $gs, 'sportName' => $gs->sport->name,
                'rows' => $rows, 'totals' => $totals, 'referenceNo' => $s->reference_no,
                'declaration' => Setting::get(Setting::DECLARATION, Setting::DEFAULT_DECLARATION),
                'nocLogo' => self::dataUri(Setting::get(Setting::NOC_LOGO)), 'gameLogo' => self::dataUri($s->game->logo_path),
            ])->render();
            $path = 'submissions/'.$s->reference_no.'/'.$gs->id.'-'.Str::slug($gs->sport->name).'.pdf';
            $disk->put($path, Pdf::loadHTML($html)->setPaper('a4')->output());
            $paths[$gs->id] = $path;
        }
        $s->forceFill(['pdf_paths' => $paths])->saveQuietly();
    }

    public static function dataUri(?string $path): ?string
    {
        $disk = Storage::disk(config('filesystems.private'));
        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
    }
}
