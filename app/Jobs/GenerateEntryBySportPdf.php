<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\Submission;
use App\Support\PdfContext;
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
        $answers = $s->answers()->get()->keyBy('game_sport_id');
        $rows = $s->game->sportsFor($s->association)->map(fn ($gs) => [
            'sport' => $gs->sport->name,
            'answer' => $answers->get($gs->id)?->answer->label() ?? 'No response',
            'value' => $answers->get($gs->id)?->answer->value,
            'note' => $answers->get($gs->id)?->note,
        ])->all();
        $html = view('pdf.entry-by-sport', [
            'game' => $s->game, 'association' => $s->association, 'rows' => $rows, 'referenceNo' => $s->reference_no,
            'declaration' => Setting::declaration($s->phase),
            ...PdfContext::for($s),
        ])->render();
        $path = 'submissions/'.$s->reference_no.'/entry-by-sport.pdf';
        Storage::disk(config('filesystems.private'))->put($path, PdfContext::pdf($html));
        $s->forceFill(['pdf_paths' => ['entry_by_sport' => $path]])->saveQuietly();
    }
}
