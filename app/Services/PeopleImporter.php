<?php

namespace App\Services;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Exports\PeopleTemplateExport;
use App\Filament\Shared\PersonForm;
use App\Models\Association;
use App\Models\Person;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenSpout\Reader\XLSX\Reader;

final class PeopleImporter
{
    public const MAX_ROWS = 500;
    private const DATE_COLUMNS = ['date_of_birth', 'passport_expiry', 'cpr_expiry'];

    public function import(string $absolutePath, Association $association): ImportResult
    {
        $rows = array_values(array_filter($this->read($absolutePath), fn (array $r) => filled($r['first_name'] ?? null) || filled($r['passport_no'] ?? null)));

        if (count($rows) > self::MAX_ROWS) {
            return new ImportResult(0, [1 => ['The file has more than '.self::MAX_ROWS.' rows. Split it into smaller files.']]);
        }

        $errors = [];
        $clean = [];
        $seen = ['passport_no_hash' => [], 'cpr_no_hash' => []];

        foreach ($rows as $i => $raw) {
            $rowNumber = $i + 2; // row 1 is the heading
            $row = $this->normalise($raw);
            $messages = $this->validate($row);

            foreach (['passport_no' => 'passport_no_hash', 'cpr_no' => 'cpr_no_hash'] as $field => $hashColumn) {
                if (blank($row[$field])) {
                    continue;
                }
                $hash = Person::blindIndex($row[$field]);
                if (isset($seen[$hashColumn][$hash])) {
                    $messages[] = "$field is repeated in row {$seen[$hashColumn][$hash]}.";
                } elseif (Person::documentExists($hashColumn, $row[$field])) {
                    $messages[] = "$field is already registered in the system.";
                }
                $seen[$hashColumn][$hash] ??= $rowNumber;
            }

            $messages ? $errors[$rowNumber] = $messages : $clean[] = $row;
        }

        if ($errors) {
            return new ImportResult(0, $errors);
        }

        DB::transaction(function () use ($clean, $association) {
            foreach ($clean as $row) {
                Person::create([...$this->toAttributes($row), 'association_id' => $association->id]);
            }
        });

        return new ImportResult(count($clean), []);
    }

    /** @return array<int, array<string, mixed>> heading => value, one per data row of the first sheet */
    private function read(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);
        $headings = null;
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $r) {
                $cells = $r->toArray();
                if ($headings === null) {
                    $headings = array_map(fn ($h) => strtolower(trim((string) $h)), $cells);
                    continue;
                }
                $row = [];
                foreach ($headings as $i => $h) {
                    if ($h !== '') {
                        $row[$h] = $cells[$i] ?? null;
                    }
                }
                $out[] = $row;
            }
            break; // first sheet only
        }
        $reader->close();

        return $out;
    }

    private function normalise(array $raw): array
    {
        $row = [];
        foreach (PeopleTemplateExport::COLUMNS as $column) {
            $value = $raw[$column] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            if (in_array($column, self::DATE_COLUMNS, true)) {
                if ($value instanceof DateTimeInterface) {
                    $value = $value->format('Y-m-d');
                } elseif (is_numeric($value)) {
                    $value = (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
                }
            }
            if (in_array($column, ['passport_no', 'cpr_no'], true) && $value !== null && $value !== '') {
                $value = (string) $value;
            }
            $row[$column] = $value === '' ? null : $value;
        }
        $row['type'] = strtolower((string) $row['type']);
        $row['gender'] = strtolower((string) $row['gender']);

        return $row;
    }

    /** @return array<int, string> */
    private function validate(array $row): array
    {
        return Validator::make($row, [
            'type' => ['required', Rule::enum(PersonType::class)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', Rule::enum(PersonGender::class)],
            'nationality' => ['required', 'string', 'max:100'],
            'height_cm' => ['nullable', 'numeric', 'between:50,250'],
            'weight_kg' => ['nullable', 'numeric', 'between:20,250'],
            'passport_no' => ['required', 'string', 'max:30'],
            'passport_expiry' => ['required', 'date_format:Y-m-d'],
            'cpr_no' => ['nullable', 'string', 'max:30'],
            'cpr_expiry' => ['nullable', 'required_with:cpr_no', 'date_format:Y-m-d'],
            'function' => ['nullable', 'required_if:type,official', Rule::in(PersonForm::FUNCTIONS)],
            'email' => ['nullable', 'email'],
        ])->errors()->all();
    }

    private function toAttributes(array $row): array
    {
        return [
            'type' => $row['type'], 'first_name' => $row['first_name'], 'second_name' => $row['second_name'],
            'third_name' => $row['third_name'], 'last_name' => $row['last_name'], 'dob' => $row['date_of_birth'],
            'gender' => $row['gender'], 'nationality' => $row['nationality'], 'height_cm' => $row['height_cm'],
            'weight_kg' => $row['weight_kg'], 'passport_no' => (string) $row['passport_no'], 'passport_expiry' => $row['passport_expiry'],
            'cpr_no' => $row['cpr_no'] !== null ? (string) $row['cpr_no'] : null, 'cpr_expiry' => $row['cpr_expiry'],
            'function' => $row['function'], 'phone' => $row['phone'], 'email' => $row['email'],
        ];
    }
}
