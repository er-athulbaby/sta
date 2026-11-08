<?php

namespace App\Services;

final readonly class ImportResult
{
    /** @param array<int, array<int, string>> $errors spreadsheet row number => messages */
    public function __construct(public int $created, public array $errors) {}

    public function ok(): bool
    {
        return $this->errors === [];
    }
}
