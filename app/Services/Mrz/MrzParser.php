<?php

namespace App\Services\Mrz;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/** ICAO 9303 TD3 (passport) machine-readable zone parser. Pure PHP, no OCR. */
final class MrzParser
{
    public function parse(string $line1, string $line2, ?CarbonInterface $today = null): MrzResult
    {
        $l1 = strtoupper(preg_replace('/\s+/', '', $line1));
        $l2 = strtoupper(preg_replace('/\s+/', '', $line2));
        if (strlen($l1) !== 44 || strlen($l2) !== 44 || $l1[0] !== 'P') {
            throw new InvalidArgumentException('Not a passport (TD3) machine-readable zone.');
        }
        $today ??= Carbon::now();
        $invalid = [];

        [$surnameRaw, $givenRaw] = array_pad(explode('<<', substr($l1, 5), 2), 2, '');
        $surname = self::name($surnameRaw);
        $given = array_values(array_filter(array_map([self::class, 'name'], explode('<', $givenRaw))));

        $doc = substr($l2, 0, 9);
        $passportNo = self::ok($doc, $l2[9]) ? rtrim($doc, '<') : null;
        if ($passportNo === null) {
            $invalid[] = 'passport_no';
        }

        $dobRaw = substr($l2, 13, 6);
        $dob = self::ok($dobRaw, $l2[19]) ? self::date($dobRaw, $today, past: true) : null;
        if ($dob === null) {
            $invalid[] = 'dob';
        }

        $expRaw = substr($l2, 21, 6);
        $expiry = self::ok($expRaw, $l2[27]) ? self::date($expRaw, $today, past: false) : null;
        if ($expiry === null) {
            $invalid[] = 'expiry';
        }

        $composite = substr($l2, 0, 10).substr($l2, 13, 7).substr($l2, 21, 22);
        if (! self::ok($composite, $l2[43])) {
            $invalid[] = 'composite';
        }

        return new MrzResult(
            surname: $surname ?: null,
            givenNames: $given,
            fullName: trim(implode(' ', [...$given, $surname])) ?: null,
            passportNo: $passportNo,
            issuingCode: rtrim(substr($l1, 2, 3), '<') ?: null,
            nationalityCode: rtrim(substr($l2, 10, 3), '<') ?: null,
            dob: $dob,
            gender: ['M' => 'male', 'F' => 'female'][$l2[20]] ?? null,
            expiry: $expiry,
            invalid: $invalid,
        );
    }

    public static function checkDigit(string $s): int
    {
        $weights = [7, 3, 1];
        $sum = 0;
        foreach (str_split($s) as $i => $c) {
            $v = ctype_digit($c) ? (int) $c : (ctype_alpha($c) ? ord($c) - 55 : 0);
            $sum += $v * $weights[$i % 3];
        }

        return $sum % 10;
    }

    private static function ok(string $field, string $digit): bool
    {
        return ctype_digit($digit) && self::checkDigit($field) === (int) $digit;
    }

    private static function name(string $raw): string
    {
        return ucwords(strtolower(trim(str_replace('<', ' ', $raw))));
    }

    private static function date(string $yymmdd, CarbonInterface $today, bool $past): ?string
    {
        if (! ctype_digit($yymmdd)) {
            return null;
        }
        $yy = (int) substr($yymmdd, 0, 2);
        $century = $past ? ($yy > (int) $today->format('y') ? 1900 : 2000) : 2000;
        $date = Carbon::createFromFormat('Y-m-d', sprintf('%04d-%s-%s', $century + $yy, substr($yymmdd, 2, 2), substr($yymmdd, 4, 2)));

        return $date && $date->format('ymd') === $yymmdd ? $date->toDateString() : null;
    }
}
