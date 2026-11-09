<?php

namespace App\Services\Phases;

use RuntimeException;

final class StaleSubmission extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This entry was changed by someone else — reload to see the latest.');
    }
}
