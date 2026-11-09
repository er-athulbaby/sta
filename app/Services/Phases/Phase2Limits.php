<?php

namespace App\Services\Phases;

use App\Enums\EventGender;
use App\Models\GameSport;

final class Phase2Limits
{
    /** @param array<int, int|null> $counts game_event_id => count @return array<string, string> */
    public function errors(GameSport $gs, array $counts): array
    {
        $gs->loadMissing(['sport', 'gameEvents.event']);
        $errors = [];
        $sums = ['men' => 0, 'women' => 0, 'total' => 0];

        foreach ($gs->gameEvents as $ge) {
            $n = (int) ($counts[$ge->id] ?? 0);
            if ($n < 0) {
                $errors["event.{$ge->id}"] = "{$ge->event->name}: enter 0 or more.";

                continue;
            }
            if ($ge->p2_max_athletes !== null && $n > $ge->p2_max_athletes) {
                $errors["event.{$ge->id}"] = "{$ge->event->name}: at most {$ge->p2_max_athletes} athletes.";
            }
            $sums['total'] += $n;
            if ($ge->event->gender === EventGender::Men) {
                $sums['men'] += $n;
            }
            if ($ge->event->gender === EventGender::Women) {
                $sums['women'] += $n;
            }
        }

        $sport = $gs->sport->name;
        if ($gs->p2_max_men !== null && $sums['men'] > $gs->p2_max_men) {
            $errors['sport.men'] = "{$sport}: men's events total {$sums['men']}, the limit is {$gs->p2_max_men}.";
        }
        if ($gs->p2_max_women !== null && $sums['women'] > $gs->p2_max_women) {
            $errors['sport.women'] = "{$sport}: women's events total {$sums['women']}, the limit is {$gs->p2_max_women}.";
        }
        if ($gs->p2_max_total !== null && $sums['total'] > $gs->p2_max_total) {
            $errors['sport.total'] = "{$sport}: total {$sums['total']}, the limit is {$gs->p2_max_total}.";
        }

        return $errors;
    }
}
