<?php

namespace App\Services\Reports;

use App\Enums\SubmissionStatus;
use App\Models\Game;
use App\Models\Person;
use App\Models\Submission;
use App\Services\Phases\ShortListChecks;
use Illuminate\Support\Collection;

/**
 * STA reports for one game (spec 6). Only submitted submissions count. Never medical data, passport or CPR numbers.
 * Every method returns ['headings' => list<string>, 'rows' => list<list<scalar|null>>].
 */
final class GameReports
{
    public const REPORTS = [
        'phase1Answers' => 'Phase 1 answers',
        'phase2Totals' => 'Phase 2 totals',
        'longList' => 'Long List',
        'shortList' => 'Short List',
        'overLimit' => 'Short List over limit',
        'expiringDocuments' => 'Expiring documents',
        'clothingSizeCounts' => 'Clothing sizes (counts)',
        'clothingSizesByPerson' => 'Clothing sizes (per person)',
    ];

    public const PERSON_HEADINGS = ['Association', 'Code', 'Name', 'Type', 'Sport', 'Event', 'Gender', 'Date of birth',
        'Age at games start', 'Nationality', 'Passport expiry'];

    public function phase1Answers(Game $game): array
    {
        $sports = $this->sportsByAssociation($game);
        $rows = [];
        foreach ($this->submitted($game, 1, ['answers']) as $s) {
            $answers = $s->answers->keyBy('game_sport_id');
            foreach ($sports->get($s->association_id, collect()) as $gs) {
                $rows[] = [$s->association->name, $gs->sport->name, $answers->get($gs->id)?->answer->label() ?? 'No response'];
            }
        }

        return ['headings' => ['Association', 'Sport', 'Answer'], 'rows' => $rows];
    }

    public function phase2Totals(Game $game): array
    {
        $rows = [];
        foreach ($this->submitted($game, 2, ['numbers', 'sportTotals']) as $s) {
            $counts = $s->numbers->pluck('count', 'game_event_id');
            $totals = $s->sportTotals->keyBy('game_sport_id');
            foreach ($game->phase2SportsFor($s->association) as $gs) {
                $total = 0;
                foreach ($gs->gameEvents->sortBy('event.name') as $ge) {
                    $n = (int) ($counts[$ge->id] ?? 0);
                    $total += $n;
                    $rows[] = [$s->association->name, $gs->sport->name, $ge->event->name, $ge->event->gender->label(), $n, null, null, null, null, null];
                }
                $t = $totals->get($gs->id);
                $rows[] = [$s->association->name, $gs->sport->name, 'Total', null, $total, $t?->male, $t?->female, $t?->gold, $t?->silver, $t?->bronze];
            }
        }

        return ['headings' => ['Association', 'Sport', 'Event', 'Gender', 'Count', 'Male participants', 'Female participants', 'Gold', 'Silver', 'Bronze'], 'rows' => $rows];
    }

    public function longList(Game $game): array
    {
        return $this->entryRows($game, 3);
    }

    public function shortList(Game $game): array
    {
        return $this->entryRows($game, 4);
    }

    public function overLimit(Game $game): array
    {
        $rows = [];
        foreach ($this->submitted($game, 4, ['entries.person']) as $s) {
            $athletes = [];
            $officials = [];
            $entries = $this->visibleEntries($game, $s);
            foreach ($entries as $e) {
                if ($e->game_event_id) {
                    $athletes[$e->game_event_id][] = $e->person_id;
                } else {
                    $officials[$e->game_sport_id][] = $e->person_id;
                }
            }
            $genders = $entries->pluck('person.gender', 'person_id')->all();
            foreach (ShortListChecks::over($game->phase2SportsFor($s->association), $athletes, $officials, $genders) as $o) {
                $rows[] = [$s->association->name, $o['sport'], ShortListChecks::KINDS[$o['kind']], $o['count'], $o['max'], $s->justification];
            }
        }

        return ['headings' => ['Association', 'Sport', 'Limit', 'Count', 'Max', 'Justification'], 'rows' => $rows];
    }

    public function expiringDocuments(Game $game): array
    {
        $rows = [];
        foreach ($this->people($game) as [$s, $p]) {
            foreach (['Passport' => $p->passport_expiry, 'CPR' => $p->cpr_expiry] as $document => $expiry) {
                if ($expiry && $expiry->lt($game->end_date)) {
                    $rows[] = [$s->association->name, $p->code, $this->name($p), $document, $expiry->toDateString()];
                }
            }
        }

        return ['headings' => ['Association', 'Code', 'Name', 'Document', 'Expiry'], 'rows' => $rows];
    }

    public function clothingSizeCounts(Game $game): array
    {
        $items = $game->clothingItems()->orderBy('name')->get();
        $byAssociation = [];
        $all = [];
        foreach ($this->people($game) as [$s, $p]) {
            $sizes = $p->sizes->pluck('size', 'clothing_item_id');
            foreach ($items as $item) {
                $size = $sizes->get($item->id);
                if ($item->applies_to->includes($p->type) && filled($size)) {
                    $byAssociation[$s->association->name][$item->id][$size] = ($byAssociation[$s->association->name][$item->id][$size] ?? 0) + 1;
                    $all[$item->id][$size] = ($all[$item->id][$size] ?? 0) + 1;
                }
            }
        }
        $rows = [];
        foreach ($all ? [...$byAssociation, 'All associations' => $all] : [] as $association => $counts) {
            foreach ($items as $item) {
                foreach ($item->sizes as $size) { // the item's own size order
                    if ($n = $counts[$item->id][$size] ?? 0) {
                        $rows[] = [$association, $item->name, $size, $n];
                    }
                }
            }
        }

        return ['headings' => ['Association', 'Item', 'Size', 'Count'], 'rows' => $rows];
    }

    public function clothingSizesByPerson(Game $game): array
    {
        $items = $game->clothingItems()->orderBy('name')->get();
        $rows = [];
        foreach ($this->people($game) as [$s, $p]) {
            $sizes = $p->sizes->pluck('size', 'clothing_item_id');
            foreach ($items->filter(fn ($i) => $i->applies_to->includes($p->type)) as $item) {
                $rows[] = [$s->association->name, $p->code, $this->name($p), $item->name, $sizes->get($item->id)];
            }
        }

        return ['headings' => ['Association', 'Code', 'Name', 'Item', 'Size'], 'rows' => $rows];
    }

    private function entryRows(Game $game, int $phase): array
    {
        $rows = [];
        foreach ($this->submitted($game, $phase, ['entries.person', 'entries.gameEvent.event', 'entries.gameSport.sport']) as $s) {
            foreach ($this->visibleEntries($game, $s) as $e) {
                $p = $e->person;
                $rows[] = [$s->association->name, $p->code, $this->name($p), $p->type->label(), $e->gameSport->sport->name,
                    $e->gameEvent?->event->name ?? 'Official', $p->gender->label(), $p->dob->toDateString(),
                    $p->age($game->start_date), $p->nationality, $p->passport_expiry?->toDateString()];
            }
        }

        return ['headings' => self::PERSON_HEADINGS,
            'rows' => collect($rows)->sortBy([[0, 'asc'], [4, 'asc'], [3, 'asc'], [2, 'asc']])->values()->all()];
    }

    /** @return Collection<int, Submission> submitted submissions of one phase, association loaded, sorted by association name */
    private function submitted(Game $game, int $phase, array $with = []): Collection
    {
        return Submission::withoutGlobalScopes()->with(['association', ...$with])
            ->where(['game_id' => $game->id, 'phase' => $phase, 'status' => SubmissionStatus::Submitted->value])
            ->get()->sortBy('association.name')->values();
    }

    /** @return Collection<int, Collection> association_id => its game sports (sport, events loaded), sorted by sport name */
    private function sportsByAssociation(Game $game): Collection
    {
        return $game->gameSports()->with(['sport', 'gameEvents.event'])->get()->sortBy('sport.name')->groupBy('sport.association_id');
    }

    /**
     * Per association: its submitted Short List (Phase 4) if any, else its submitted Long List (Phase 3).
     * Shared with the game system upload files (M4.2); filter entries with visibleEntries().
     *
     * @return Collection<int, Submission> association loaded, sorted by association name
     */
    public function includedSubmissions(Game $game, array $with = []): Collection
    {
        return $this->submitted($game, 4, $with)->keyBy('association_id')
            ->union($this->submitted($game, 3, $with)->keyBy('association_id'))
            ->sortBy('association.name')->values();
    }

    /**
     * Distinct people per association from includedSubmissions().
     *
     * @return Collection<int, array{0: Submission, 1: Person}>
     */
    private function people(Game $game): Collection
    {
        return $this->includedSubmissions($game, ['entries.person.sizes'])
            ->flatMap(fn (Submission $s) => $this->visibleEntries($game, $s)->pluck('person')->unique('id')->sortBy('code')
                ->map(fn (Person $p) => [$s, $p]))->values();
    }

    /** Entries in sports and events still open to the association (not answered "no" in Phase 1), like the PDFs. */
    public function visibleEntries(Game $game, Submission $s): Collection
    {
        $sports = $game->phase2SportsFor($s->association);
        $sportIds = $sports->pluck('id');
        $eventIds = $sports->flatMap->gameEvents->pluck('id');

        return $s->entries->filter(fn ($e) => $e->game_event_id ? $eventIds->contains($e->game_event_id) : $sportIds->contains($e->game_sport_id));
    }

    private function name(Person $p): string
    {
        return $p->full_name ?: $p->fullName();
    }
}
