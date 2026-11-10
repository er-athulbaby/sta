<?php

namespace App\Exports;

use App\Models\ExtraField;
use App\Models\User;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeopleTemplateExport
{
    public const COLUMNS = [
        'type', 'full_name', 'first_name', 'second_name', 'third_name', 'last_name', 'date_of_birth', 'gender', 'nationality',
        'height_cm', 'weight_kg', 'passport_no', 'passport_issuing_country', 'passport_issue_date', 'passport_expiry', 'cpr_no', 'cpr_expiry', 'function', 'mobile', 'email',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows  rows keyed by columns(); empty = one example row
     * @param  ?User  $user  sensitive extra-field columns are left out unless the user may view medical data; null = all
     */
    public function __construct(private readonly array $rows = [], private readonly ?User $user = null) {}

    /** @return list<string> the fixed columns plus one per active extra field the user may see (spec 15.1) */
    public static function columns(?User $user = null): array
    {
        return [...self::COLUMNS, ...ExtraField::activeFor($user)->pluck('key')->all()];
    }

    /** @return array<int, array<int, mixed>> */
    private function data(): array
    {
        $rows = $this->rows ?: [[
            'type' => 'athlete', 'full_name' => 'Ahmed Ali Hassan', 'first_name' => 'Ahmed', 'second_name' => 'Ali', 'third_name' => '', 'last_name' => 'Hassan',
            'date_of_birth' => '2006-05-20', 'gender' => 'male', 'nationality' => 'Bahrain', 'height_cm' => 180, 'weight_kg' => 75,
            'passport_no' => 'BH0000000', 'passport_issuing_country' => 'Bahrain', 'passport_issue_date' => '2023-01-01', 'passport_expiry' => '2030-12-31', 'cpr_no' => '060520000', 'cpr_expiry' => '2029-12-31',
            'function' => '', 'mobile' => '', 'email' => '',
        ]];
        $columns = self::columns($this->user);

        return array_map(fn (array $r) => array_map(fn ($c) => $r[$c] ?? '', $columns), $rows);
    }

    public function writeTo(string $absolutePath): void
    {
        $writer = new Writer;
        $writer->openToFile($absolutePath);
        $writer->addRow(LiteralRow::from(self::columns($this->user)));
        foreach ($this->data() as $values) {
            $writer->addRow(LiteralRow::from($values));
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
