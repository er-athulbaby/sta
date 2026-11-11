<?php

namespace App\Support;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\GamePhase;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/** Canvas design tokens and small display helpers shared by Blade views and PDFs (spec 17). */
final class Sta
{
    public const FONTS_URL = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap';

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
    public const CELL_LABELS = ['submitted' => 'Submitted', 'awaiting' => 'Awaiting admin', 'draft' => 'In progress',
        'overdue' => 'Overdue', 'no_response' => 'No response'];

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

    /** Page heading with optional phase chip and status pill (rendered inside Filament's <h1>). */
    public static function heading(string $title, ?int $phase = null, ?string $phaseLabel = null, ?string $pill = null, ?string $pillLabel = null): HtmlString
    {
        return new HtmlString(Blade::render(
            '<x-sta.heading :title="$title" :phase="$phase" :phase-label="$phaseLabel" :pill="$pill" :pill-label="$pillLabel" />',
            compact('title', 'phase', 'phaseLabel', 'pill', 'pillLabel')));
    }
}
