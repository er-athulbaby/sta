<?php

namespace App\Jobs;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Submission;
use App\Support\PdfContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateLongListPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    /** pdf_paths key, file name and title; GenerateShortListPdf overrides them for Phase 4. */
    protected const KEY = 'long_list';

    protected const FILE = 'long-list.pdf';

    protected const TITLE = 'Long List';

    public function __construct(public Submission $submission) {}

    public function handle(): void
    {
        $s = $this->submission->fresh();
        $path = 'submissions/'.$s->reference_no.'/'.static::FILE;
        Storage::disk(config('filesystems.private'))->put($path, $this->output());
        $s->forceFill(['pdf_paths' => [static::KEY => $path]])->saveQuietly();
    }

    /** The PDF bytes for the saved entries (also the portal "Preview PDF"). */
    public function output(): string
    {
        $s = $this->submission->fresh(['game.clothingItems', 'association']);
        $sports = $s->game->phase2SportsFor($s->association)->keyBy('id');
        $entries = $s->entries()->get()->filter(fn ($e) => $sports->has($e->game_sport_id));
        $people = Person::withoutGlobalScopes()->with('sizes')->where('association_id', $s->association_id)
            ->whereKey($entries->pluck('person_id')->unique()->all())->get()->keyBy('id');
        $entries = $entries->filter(fn ($e) => $people->has($e->person_id));
        $items = $s->game->clothingItems->sortBy('name')->values();
        $athleteItems = $items->filter(fn ($i) => $i->applies_to->includes(PersonType::Athlete))->values();
        $officialItems = $items->filter(fn ($i) => $i->applies_to->includes(PersonType::Official))->values();

        // One row per athlete per sport, the events joined.
        $athletes = $entries->whereNotNull('game_event_id')->groupBy(fn ($e) => $e->person_id.'-'.$e->game_sport_id)
            ->map(function ($group) use ($people, $sports, $athleteItems) {
                $p = $people[$group->first()->person_id];
                $gs = $sports[$group->first()->game_sport_id];
                $sizes = $p->sizes->pluck('size', 'clothing_item_id');

                return [
                    'photo' => GenerateEntryByEventPdfs::dataUri($p->photo_path),
                    'name' => $p->full_name ?: $p->fullName(),
                    'sport' => $gs->sport->name,
                    'events' => $gs->gameEvents->whereIn('id', $group->pluck('game_event_id')->all())->map(fn ($ge) => $ge->event->name)->sort()->implode(', '),
                    'dob' => $p->dob->format('d M Y'),
                    'sizes' => $athleteItems->map(fn ($i) => $sizes->get($i->id, ''))->all(),
                    'passport' => filled($p->passport_scan_path),
                ];
            })->sortBy([['sport', 'asc'], ['name', 'asc']])->values()->all();

        // One row per official, whatever the number of sports.
        $officials = $entries->whereNull('game_event_id')->pluck('person_id')->unique()
            ->map(function ($id) use ($people, $officialItems) {
                $p = $people[$id];
                $sizes = $p->sizes->pluck('size', 'clothing_item_id');

                return [
                    'photo' => GenerateEntryByEventPdfs::dataUri($p->photo_path),
                    'name' => $p->full_name ?: $p->fullName(),
                    'function' => $p->function,
                    'dob' => $p->dob->format('d M Y'),
                    'phone' => filled($p->phone) ? $p->phone_cc.' '.$p->phone : '',
                    'email' => $p->email ?? '',
                    'sizes' => $officialItems->map(fn ($i) => $sizes->get($i->id, ''))->all(),
                    'passport' => filled($p->passport_scan_path),
                ];
            })->sortBy('name')->values()->all();

        $athleteIds = $entries->whereNotNull('game_event_id')->pluck('person_id')->unique();
        $summary = [
            'male' => $athleteIds->filter(fn ($id) => $people[$id]->gender === PersonGender::Male)->count(),
            'female' => $athleteIds->filter(fn ($id) => $people[$id]->gender === PersonGender::Female)->count(),
            'officials' => count($officials),
        ];

        $html = view('pdf.long-list', [
            'summary' => $summary,
            'game' => $s->game, 'association' => $s->association, 'athletes' => $athletes, 'officials' => $officials,
            'athleteItems' => $athleteItems->pluck('name')->all(), 'officialItems' => $officialItems->pluck('name')->all(), 'referenceNo' => $s->reference_no,
            'declaration' => Setting::declaration($s->phase),
            ...PdfContext::for($s),
            'title' => static::TITLE, 'justification' => $s->justification, 'overLimits' => $this->overLimits($s),
        ])->render();

        return PdfContext::pdf($html, 'landscape');
    }

    /** @return list<string> the limits the list exceeds, for the justification box (Short List only). */
    protected function overLimits(Submission $s): array
    {
        return [];
    }
}
