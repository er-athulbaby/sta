<?php

namespace App\Support;

use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/** Shared data for the four phase PDFs (spec 17): logos, who prepared and approved it, and the phase deadline. */
final class PdfContext
{
    /** @return array{phase: int, nocLogo: string, gameLogo: ?string, assocLogo: ?string, preparedBy: ?string, approvedBy: ?string, deadline: ?string, submittedLine: ?string} */
    public static function for(Submission $s): array
    {
        $s->loadMissing(['game', 'association']);
        $who = function (?int $id, ?CarbonInterface $at): ?string {
            $u = $id ? User::find($id) : null;

            return $u ? $u->name.', '.$u->role->label().($at ? ' · '.$at->format('j M Y') : '') : null;
        };

        return [
            'phase' => (int) $s->phase,
            'nocLogo' => GenerateEntryByEventPdfs::dataUri(Setting::get(Setting::NOC_LOGO)) ?? self::bocLogo(),
            'gameLogo' => GenerateEntryByEventPdfs::dataUri($s->game->logo_path),
            'assocLogo' => GenerateEntryByEventPdfs::dataUri($s->association->logo_path),
            'preparedBy' => $who($s->submitted_by, $s->submitted_at),
            'approvedBy' => $who($s->approved_by, $s->approved_at),
            'deadline' => $s->gamePhase()->effectiveDeadline($s)?->format('j M Y'),
            // Phase 3/4 sample: "Submitted 14 Oct 2026 by Mariam Khalil, Association Editor".
            'submittedLine' => ($u = $s->submitted_by ? User::find($s->submitted_by) : null)
                ? 'Submitted '.($s->submitted_at ? $s->submitted_at->format('j M Y').' ' : '').'by '.$u->name.', '.$u->role->label() : null,
        ];
    }

    /** The BOC logo bundled with the app, used when Settings has no NOC logo (dompdf reads PNG, not WebP). */
    public static function bocLogo(): string
    {
        return 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/boc-logo.png')));
    }

    /**
     * Renders an A4 PDF and writes "Page N of M" at the right end of the footer line on every page.
     * dompdf 3 has no CSS counter(pages), and a canvas callback avoids turning on isPhpEnabled.
     */
    public static function pdf(string $html, string $orientation = 'portrait'): string
    {
        // DejaVu's glyph box is ~1.16em and dompdf scales line-height by it (x1.1 by default), so a ratio of 0.86
        // makes CSS line-height mean what it means in the browser, as on the samples.
        $pdf = Pdf::setOption('fontHeightRatio', 0.86)->setOption('isFontSubsettingEnabled', true)->loadHTML($html)->setPaper('a4', $orientation);
        $pdf->render();
        // Footer baseline and right edge in pt, measured from the rendered footer (48px / 40px page margins).
        [$right, $baseline] = $orientation === 'landscape' ? [30, 32.0] : [36, 38.0];
        $pdf->getCanvas()->page_script(function (int $page, int $pages, Canvas $canvas, FontMetrics $metrics) use ($right, $baseline) {
            $font = $metrics->getFont('DejaVu Sans');
            $text = "Page {$page} of {$pages}";
            $x = $canvas->get_width() - $right - $metrics->getTextWidth($text, $font, 9);
            $y = $canvas->get_height() - $baseline - $metrics->getFontBaseline($font, 9);
            $canvas->text($x, $y, $text, $font, 9, [0.278, 0.333, 0.412]); // #475569
        });

        return $pdf->output();
    }

    /** Header dates as on the samples: "20 – 30 Nov 2026", "28 Nov – 3 Dec 2026", or one date. */
    public static function dates(?CarbonInterface $from, ?CarbonInterface $to): string
    {
        if (! $from || ! $to) {
            return ($from ?? $to)?->format('j M Y') ?? '';
        }
        $start = match (true) {
            $from->format('Y-m') === $to->format('Y-m') => $from->format('j'),
            $from->year === $to->year => $from->format('j M'),
            default => $from->format('j M Y'),
        };

        return $start.' – '.$to->format('j M Y');
    }
}
