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

class GenerateEntryBySportPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    public function __construct(public Submission $submission) {}

    public function handle(): void
    {
        $s = $this->submission->fresh(['game', 'association']);
        $answers = $s->answers()->pluck('answer', 'game_sport_id');
        $rows = $s->game->sportsFor($s->association)->map(fn ($gs) => [
            'sport' => $gs->sport->name,
            'answer' => isset($answers[$gs->id]) ? $answers[$gs->id]->label() : 'No response',
        ])->all();
        $html = view('pdf.entry-by-sport', [
            'game' => $s->game, 'association' => $s->association, 'rows' => $rows, 'referenceNo' => $s->reference_no,
            'declaration' => Setting::get(Setting::DECLARATION, Setting::DEFAULT_DECLARATION),
            'nocLogo' => GenerateEntryByEventPdfs::dataUri(Setting::get(Setting::NOC_LOGO)),
            'gameLogo' => GenerateEntryByEventPdfs::dataUri($s->game->logo_path),
        ])->render();
        $path = 'submissions/'.$s->reference_no.'/entry-by-sport.pdf';
        Storage::disk(config('filesystems.private'))->put($path, Pdf::loadHTML($html)->setPaper('a4')->output());
        $s->forceFill(['pdf_paths' => ['entry_by_sport' => $path]])->saveQuietly();
    }
}
