<?php

namespace App\Services\Phases;

use App\Models\GameSport;

final class Phase2Limits
{
    /**
     * Spec 16.2: event counts are limited only by each event's maximum; p2_max_men/women/total limit the participant totals.
     *
     * @param  array<int, int|null>  $counts  game_event_id => count
     * @param  array{male?: int|null, female?: int|null}  $totals  participants entered for the sport; a blank one is not checked
     * @return array<string, string> keyed event.{gameEventId} or sport.{male|female|total}
     */
    public function errors(GameSport $gs, array $counts, array $totals = []): array
    {
        $gs->loadMissing(['sport', 'gameEvents.event']);
        $errors = [];

        foreach ($gs->gameEvents as $ge) {
            $n = (int) ($counts[$ge->id] ?? 0);
            if ($n < 0) {
                $errors["event.{$ge->id}"] = "{$ge->event->name}: enter 0 or more.";

                continue;
            }
            if ($ge->p2_max_athletes !== null && $n > $ge->p2_max_athletes) {
                $errors["event.{$ge->id}"] = "{$ge->event->name}: at most {$ge->p2_max_athletes} athletes.";
            }
        }

        $sport = $gs->sport->name;
        $male = $totals['male'] ?? null;
        $female = $totals['female'] ?? null;
        if ($male !== null && $gs->p2_max_men !== null && $male > $gs->p2_max_men) {
            $errors['sport.male'] = "{$sport}: {$male} male participants, the limit is {$gs->p2_max_men}.";
        }
        if ($female !== null && $gs->p2_max_women !== null && $female > $gs->p2_max_women) {
            $errors['sport.female'] = "{$sport}: {$female} female participants, the limit is {$gs->p2_max_women}.";
        }
        $total = (int) $male + (int) $female;
        if (($male !== null || $female !== null) && $gs->p2_max_total !== null && $total > $gs->p2_max_total) {
            $errors['sport.total'] = "{$sport}: {$total} participants in total, the limit is {$gs->p2_max_total}.";
        }

        return $errors;
    }
}
