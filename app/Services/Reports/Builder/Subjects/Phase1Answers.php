<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\InterestAnswerValue;
use App\Enums\SubmissionStatus;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a Phase 1 answer (Yes / No / Maybe) for a sport. */
final class Phase1Answers extends Subject
{
    public function key(): string
    {
        return 'phase1_answers';
    }

    public function label(): string
    {
        return 'Phase 1 answers';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('interest_answers')
            ->join('submissions', 'submissions.id', '=', 'interest_answers.submission_id')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->join('game_sports', 'game_sports.id', '=', 'interest_answers.game_sport_id')
            ->join('sports', 'sports.id', '=', 'game_sports.sport_id')
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['association', 'sport', 'answer'];
    }

    protected function define(): array
    {
        return [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('sport', 'Sport', 'Answer', 'sports.name'),
            Field::enum('answer', 'Answer', 'Answer', 'interest_answers.answer', Field::labels(InterestAnswerValue::class)),
            Field::enum('status', 'Submission status', 'Answer', 'submissions.status', Field::labels(SubmissionStatus::class)),
        ];
    }
}
