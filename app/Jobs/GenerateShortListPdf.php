<?php

namespace App\Jobs;

use App\Models\Submission;
use App\Services\Phases\ShortListChecks;

/** Phase 4 PDF: the Long List layout (spec: "layout from the Phase 3 sample") titled Short List, plus the justification. */
class GenerateShortListPdf extends GenerateLongListPdf
{
    protected const KEY = 'short_list';

    protected const FILE = 'short-list.pdf';

    protected const TITLE = 'Short List';

    /** As on the PdfPhase4 sample: "Weightlifting — Male: 4 of max 3". */
    protected function overLimits(Submission $s): array
    {
        $kinds = ['men' => 'Male', 'women' => 'Female', 'officials' => 'Team officials'];

        return array_map(fn ($o) => "{$o['sport']} — {$kinds[$o['kind']]}: {$o['count']} of max {$o['max']}",
            app(ShortListChecks::class)->overLimits($s));
    }
}
