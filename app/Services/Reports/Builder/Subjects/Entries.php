<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\SubmissionStatus;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Sql;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a Long List (Phase 3) or Short List (Phase 4) entry. */
final class Entries extends Subject
{
    public function key(): string
    {
        return 'entries';
    }

    public function label(): string
    {
        return 'Entries (Long and Short List)';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('entries')
            ->join('submissions', 'submissions.id', '=', 'entries.submission_id')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->join('people', 'people.id', '=', 'entries.person_id')
            ->join('game_sports', 'game_sports.id', '=', 'entries.game_sport_id')
            ->join('sports', 'sports.id', '=', 'game_sports.sport_id')
            ->leftJoin('game_events', 'game_events.id', '=', 'entries.game_event_id')
            ->leftJoin('events', 'events.id', '=', 'game_events.event_id')
            ->whereIn('submissions.phase', [3, 4])
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['full_name', 'association', 'sport', 'event', 'phase'];
    }

    protected function define(): array
    {
        return [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::enum('phase', 'List', 'Game', 'submissions.phase', [3 => 'Long List', 4 => 'Short List']),
            Field::enum('status', 'Submission status', 'Game', 'submissions.status', Field::labels(SubmissionStatus::class)),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('sport', 'Sport', 'Entry', 'sports.name'),
            Field::text('event', 'Event', 'Entry', "COALESCE(events.name, 'Official')"),
            Field::enum('event_gender', 'Event gender', 'Entry', 'events.gender', Field::labels(EventGender::class)),
            Field::text('code', 'Person code', 'Person', 'people.code'),
            Field::text('full_name', 'Name', 'Person', Sql::fullName()),
            Field::enum('type', 'Type', 'Person', 'people.type', Field::labels(PersonType::class)),
            Field::enum('gender', 'Gender', 'Person', 'people.gender', Field::labels(PersonGender::class)),
            Field::date('dob', 'Date of birth', 'Person', 'people.dob'),
            Field::number('age', 'Age at game start', 'Person', Sql::age('people.dob', 'games.start_date')),
            Field::text('nationality', 'Nationality', 'Person', 'people.nationality'),
            Field::date('passport_expiry', 'Passport expiry', 'Person', 'people.passport_expiry'),
        ];
    }
}
