<?php

namespace App\Support;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Event;
use App\Models\GamePhase;
use Carbon\CarbonInterface;

/** Canvas design tokens and small display helpers shared by Blade views and PDFs (spec 17). */
final class Sta
{
    /** Association colours of the canvas (docs/design/canvas/project/Main.dc.html, the LC map). */
    public const AVATAR_COLOURS = ['#9A3412', '#3730A3', '#15803D', '#86198F', '#0F766E', '#BE185D', '#374151', '#854D0E', '#4D7C0F', '#0369A1'];

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(implode('', array_map(fn (string $w) => mb_substr($w, 0, 1), array_slice($words, 0, 2)))) ?: '?';
    }

    public static function avatarColour(string $key): string
    {
        return self::AVATAR_COLOURS[crc32($key) % count(self::AVATAR_COLOURS)];
    }

    /** Game dashboard cell statuses (GameProgress::status()) and their canvas labels. */
    public const CELL_LABELS = ['submitted' => 'Submitted', 'awaiting' => 'Awaiting admin', 'draft' => 'Draft',
        'overdue' => 'Overdue', 'no_response' => 'Not started'];

    public static function cellPill(string $status): string
    {
        return ['submitted' => 'submitted', 'awaiting' => 'awaiting', 'draft' => 'draft', 'overdue' => 'overdue'][$status] ?? 'none';
    }

    public static function submissionPill(SubmissionStatus $s): string
    {
        return match ($s) {
            SubmissionStatus::Submitted => 'submitted',
            SubmissionStatus::AwaitingAssocAdmin => 'awaiting',
            SubmissionStatus::Draft => 'draft',
        };
    }

    /** @return array{0: string, 1: string} [label, pill key] */
    public static function phaseStatus(GamePhase $p): array
    {
        if (! $p->enabled) {
            return ['Skipped', 'skipped'];
        }

        return match ($p->status) {
            PhaseStatus::Open => ['Open', 'open'],
            PhaseStatus::Closed => ['Closed', 'closed'],
            PhaseStatus::Draft => ['Not opened', 'none'],
        };
    }

    public static function daysLeft(?CarbonInterface $deadline): ?string
    {
        if ($deadline === null) {
            return null;
        }
        if ($deadline->isPast()) {
            return 'Overdue';
        }
        $days = (int) floor(now()->diffInDays($deadline));

        return match ($days) {
            0 => 'Due today',
            1 => '1 day left',
            default => $days.' days left',
        };
    }

    /** "Today, 10:42", "Yesterday" or "7 Oct" (canvas Last activity). */
    public static function when(CarbonInterface $t): string
    {
        $t = $t->copy()->timezone('Asia/Bahrain');
        $today = now('Asia/Bahrain');

        return match (true) {
            $t->isSameDay($today) => 'Today, '.$t->format('H:i'),
            $t->isSameDay($today->copy()->subDay()) => 'Yesterday',
            default => $t->format('j M'),
        };
    }

    /** Event label with its gender ("Male 60kg") unless the event name already says it. */
    public static function eventLabel(Event $event): string
    {
        return preg_match('/\b(male|female|mixed|men|women)\b/i', $event->name) ? $event->name : $event->gender->label().' '.$event->name;
    }
}
