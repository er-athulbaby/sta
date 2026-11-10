<?php

namespace App\Jobs;

/** Phase 4 PDF: the Long List layout (spec: "layout from the Phase 3 sample") titled Short List, plus the justification. */
class GenerateShortListPdf extends GenerateLongListPdf
{
    protected const KEY = 'short_list';

    protected const FILE = 'short-list.pdf';

    protected const TITLE = 'Short List';
}
