<?php

namespace App\Services\Reports\Builder\Subjects;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\Person;
use App\Services\Reports\Builder\Field;
use App\Services\Reports\Builder\Sql;
use App\Services\Reports\Builder\Subject;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One row is a registry person. Admin-only (no association has the builder), so no tenant scope applies. */
final class People extends Subject
{
    public function key(): string
    {
        return 'people';
    }

    public function label(): string
    {
        return 'People';
    }

    public function gameBased(): bool
    {
        return false;
    }

    /** Person ids on any Long or Short List of the game (subquery for whereIn). */
    public static function onLists(int $gameId): Closure
    {
        return fn (Builder $q) => $q->select('entries.person_id')->from('entries')
            ->join('submissions', 'submissions.id', '=', 'entries.submission_id')
            ->where('submissions.game_id', $gameId);
    }

    public function query(?int $gameId): Builder
    {
        return DB::table('people')
            ->join('associations', 'associations.id', '=', 'people.association_id')
            ->when($gameId, fn (Builder $q) => $q->whereIn('people.id', self::onLists($gameId)));
    }

    public function defaultColumns(): array
    {
        return ['full_name', 'code', 'type', 'gender', 'dob', 'association'];
    }

    protected function define(): array
    {
        // Same rule as Person::missingFields(), as "filled or not" only: cpr_no's value never leaves the database.
        $complete = fn (string $type) => implode(' AND ', array_map(fn (string $c) => Sql::filled("people.{$c}", $c === 'passport_issue_date'), array_keys(Person::REQUIRED_FOR_COMPLETE[$type])));

        return [
            Field::text('full_name', 'Name', 'Person', Sql::fullName()),
            Field::text('code', 'Person code', 'Person', 'people.code'),
            Field::enum('type', 'Type', 'Person', 'people.type', Field::labels(PersonType::class)),
            Field::enum('gender', 'Gender', 'Person', 'people.gender', Field::labels(PersonGender::class)),
            Field::text('nationality', 'Nationality', 'Person', 'people.nationality'),
            Field::date('dob', 'Date of birth', 'Person', 'people.dob'),
            Field::number('age', 'Age (today)', 'Person', Sql::age('people.dob', Sql::today())),
            Field::text('function', 'Function', 'Person', 'people.function'),
            Field::boolean('complete', 'Complete', 'Person',
                "(CASE WHEN people.type = 'athlete' THEN ({$complete('athlete')}) ELSE ({$complete('official')}) END)"),
            Field::text('association', 'Association', 'Association', 'associations.name'),
            Field::text('association_code', 'Association code', 'Association', 'associations.code'),
            Field::date('passport_expiry', 'Passport expiry', 'Documents', 'people.passport_expiry'),
            Field::date('cpr_expiry', 'CPR expiry', 'Documents', 'people.cpr_expiry'),
            Field::boolean('has_photo', 'Has photo', 'Documents', Sql::filled('people.photo_path')),
            Field::boolean('has_passport_scan', 'Has passport scan', 'Documents', Sql::filled('people.passport_scan_path')),
        ];
    }
}
