<?php

namespace App\Exports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeopleTemplateExport
{
    public const COLUMNS = [
        'type', 'first_name', 'second_name', 'third_name', 'last_name', 'date_of_birth', 'gender', 'nationality',
        'height_cm', 'weight_kg', 'passport_no', 'passport_expiry', 'cpr_no', 'cpr_expiry', 'function', 'phone', 'email',
    ];

    /** @param array<int, array<string, mixed>> $rows rows keyed by COLUMNS; empty = one example row */
    public function __construct(private readonly array $rows = []) {}

    /** @return array<int, array<int, mixed>> */
    private function data(): array
    {
        $rows = $this->rows ?: [[
            'type' => 'athlete', 'first_name' => 'Ahmed', 'second_name' => 'Ali', 'third_name' => '', 'last_name' => 'Hassan',
            'date_of_birth' => '2006-05-20', 'gender' => 'male', 'nationality' => 'Bahrain', 'height_cm' => 180, 'weight_kg' => 75,
            'passport_no' => 'BH0000000', 'passport_expiry' => '2030-12-31', 'cpr_no' => '060520000', 'cpr_expiry' => '2029-12-31',
            'function' => '', 'phone' => '', 'email' => '',
        ]];

        return array_map(fn (array $r) => array_map(fn ($c) => $r[$c] ?? '', self::COLUMNS), $rows);
    }

    public function writeTo(string $absolutePath): void
    {
        $writer = new Writer();
        $writer->openToFile($absolutePath);
        $writer->addRow(Row::fromValues(self::COLUMNS));
        foreach ($this->data() as $values) {
            $writer->addRow(Row::fromValues($values));
        }
        $writer->close();
    }

    public function download(string $filename = 'people-template.xlsx'): StreamedResponse
    {
        return response()->streamDownload(function () {
            $tmp = tempnam(sys_get_temp_dir(), 'tpl');
            $this->writeTo($tmp);
            readfile($tmp);
            unlink($tmp);
        }, $filename);
    }
}
