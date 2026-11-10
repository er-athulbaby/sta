<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\SubmissionStatus;
use App\Models\GamePhase;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a submission (one association, one phase). Short List over-limits stay in the ready-made report. */
final class Submissions extends Subject
{
    public function key(): string
    {
        return 'submissions';
    }

    public function label(): string
    {
        return 'Submissions';
    }

    public function gameBased(): bool
    {
        return true;
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('submissions')
            ->join('games', 'games.id', '=', 'submissions.game_id')
            ->join('associations', 'associations.id', '=', 'submissions.association_id')
            ->leftJoin('game_phases', fn ($j) => $j->on('game_phases.game_id', '=', 'submissions.game_id')->on('game_phases.phase', '=', 'submissions.phase'))
            ->where('submissions.game_id', $gameId);
    }

    public function defaultColumns(): array
    {
        return ['association', 'phase', 'status', 'reference', 'submitted_at'];
    }

    protected function define(): array
    {
        $phases = [];
        foreach (GamePhase::NAMES as $n => $name) {
            $phases[$n] = "Phase {$n} – {$name}";
        }

        return [
            Field::text('game', 'Game', 'Game', 'games.name'),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::enum('phase', 'Phase', 'Submission', 'submissions.phase', $phases),
            Field::enum('status', 'Status', 'Submission', 'submissions.status', Field::labels(SubmissionStatus::class)),
            Field::text('reference', 'Reference', 'Submission', 'submissions.reference_no'),
            Field::number('revision', 'Revision', 'Submission', 'submissions.revision'),
            Field::date('submitted_at', 'Submitted at', 'Submission', 'submissions.submitted_at'),
            Field::date('approved_at', 'Approved at', 'Submission', 'submissions.approved_at'),
            Field::date('deadline', 'Deadline (effective)', 'Submission', 'COALESCE(submissions.deadline_override, game_phases.deadline)'),
            Field::text('justification', 'Justification', 'Submission', 'submissions.justification'),
        ];
    }
}
