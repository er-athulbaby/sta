<?php

namespace App\Exports;

use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** One-sheet Excel download of literal rows (shared by the ready-made reports and the report builder). */
final class Xlsx
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<array<int|string, mixed>>  $rows
     */
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
            try {
                $writer = new Writer;
                $writer->openToFile($tmp);
                $writer->addRow(LiteralRow::from($headings));
                foreach ($rows as $row) {
                    $writer->addRow(LiteralRow::from(array_values($row)));
                }
                $writer->close();
                readfile($tmp);
            } finally {
                @unlink($tmp);
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
