<?php

namespace App\Services\Reports\Builder;

use App\Models\User;
use Illuminate\Database\Query\Builder;

/** A reportable subject (spec 14): where its rows come from and the fields it offers. Columns are never exposed automatically. */
abstract class Subject
{
    abstract public function key(): string;

    abstract public function label(): string;

    /** True when a game must be chosen (Entries, Submissions, Phase 1 answers, Phase 2 numbers). */
    abstract public function gameBased(): bool;

    /** The subject's rows, narrowed to the game when one is given. No select, no order: the engine adds them. */
    abstract public function query(?int $gameId): Builder;

    /** @return list<string> the first one is also the default sort */
    abstract public function defaultColumns(): array;

    /** @return list<Field> */
    abstract protected function define(): array;

    /**
     * The fields, keyed. The engine and the validator read fields only from here, so a subject can append dynamic
     * fields (spec 15 extra fields, M4.2) by overriding this method. $user decides which of those they may see
     * (sensitive extra fields); nothing is cached, so one user's fields never reach another's call.
     *
     * @return array<string, Field>
     */
    public function fields(?User $user = null): array
    {
        $fields = [];
        foreach ($this->define() as $field) {
            $fields[$field->key] = $field;
        }

        return $fields;
    }
}
