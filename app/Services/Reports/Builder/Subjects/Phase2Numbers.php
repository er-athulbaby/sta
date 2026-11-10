<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\EventGender;
use App\Enums\SubmissionStatus;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a Phase 2 number for an event. */
final class Phase2Numbers extends Subject
{
    public function key(): string
    {
        return 'phase2_numbers';
    }

    public function label(): string
    {
        return 'Phase 2 numbers';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('number_entries')
            ->join('submissions', 'submissions.id', '=', 'number_entries.submission_id')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->join('game_events', 'game_events.id', '=', 'number_entries.game_event_id')
            ->join('events', 'events.id', '=', 'game_events.event_id')
            ->join('game_sports', 'game_sports.id', '=', 'game_events.game_sport_id')
            ->join('sports', 'sports.id', '=', 'game_sports.sport_id')
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['association', 'sport', 'event', 'count'];
    }

    protected function define(): array
    {
        return [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('sport', 'Sport', 'Number', 'sports.name'),
            Field::text('event', 'Event', 'Number', 'events.name'),
            Field::enum('gender', 'Event gender', 'Number', 'events.gender', Field::labels(EventGender::class)),
            Field::number('count', 'Count', 'Number', 'number_entries.count'),
            Field::enum('status', 'Submission status', 'Number', 'submissions.status', Field::labels(SubmissionStatus::class)),
        ];
    }
}
