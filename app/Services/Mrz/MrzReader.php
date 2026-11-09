<?php

namespace App\Services\Mrz;

interface MrzReader
{
    /** @return array{0: string, 1: string}|null the two TD3 lines, or null when no MRZ was found */
    public function read(string $absolutePath): ?array;
}
