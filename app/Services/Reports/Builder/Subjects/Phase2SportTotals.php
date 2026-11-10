<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\SubmissionStatus;
use App\Models\Phase2SportTotal;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a Phase 2 sport total: participants and expected medals per submission and game sport (spec 16.2). */
final class Phase2SportTotals extends Subject
{
    public function key(): string
    {
        return 'phase2_sport_totals';
    }

    public function label(): string
    {
        return 'Phase 2 sport totals';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('phase2_sport_totals')
            ->join('submissions', 'submissions.id', '=', 'phase2_sport_totals.submission_id')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->join('game_sports', 'game_sports.id', '=', 'phase2_sport_totals.game_sport_id')
            ->join('sports', 'sports.id', '=', 'game_sports.sport_id')
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['association', 'sport', 'male', 'female', 'gold', 'silver', 'bronze'];
    }

    protected function define(): array
    {
        $fields = [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('sport', 'Sport', 'Totals', 'sports.name'),
            Field::enum('status', 'Submission status', 'Totals', 'submissions.status', Field::labels(SubmissionStatus::class)),
        ];
        foreach (Phase2SportTotal::LABELS as $column => $label) {
            $fields[] = Field::number($column, $label, 'Totals', "phase2_sport_totals.{$column}");
        }

        return $fields;
    }
}
