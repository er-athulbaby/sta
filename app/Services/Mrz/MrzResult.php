<?php

namespace App\Services\Mrz;

final readonly class MrzResult
{
    /** @param array<int, string> $givenNames @param array<int, string> $invalid */
    public function __construct(
        public ?string $surname, public array $givenNames, public ?string $fullName, public ?string $passportNo,
        public ?string $issuingCode, public ?string $nationalityCode, public ?string $dob, public ?string $gender,
        public ?string $expiry, public array $invalid,
    ) {}
}
