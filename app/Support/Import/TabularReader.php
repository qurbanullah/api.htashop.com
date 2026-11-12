<?php

namespace App\Support\Import;

use Generator;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads a CSV, XLSX or ODS file as a stream of associative rows.
 *
 * CSV is streamed cell by cell (constant memory). Spreadsheet files are read in
 * 1,000-row windows; PhpSpreadsheet still holds the workbook, so very large
 * spreadsheets should be exported to CSV first.
 */
class TabularReader
{
    private const CHUNK = 1000;

    public function __construct(
        private readonly string $path,
        private readonly ?string $sheet = null,
    ) {}

    public static function format(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'csv', 'txt' => 'csv',
            'xlsx' => 'xlsx',
            'ods' => 'ods',
            default => throw new InvalidArgumentException(
                'Unsupported file type. Upload a .csv, .xlsx or .ods file.'
            ),
        };
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function rows(): Generator
    {
        if (! is_file($this->path)) {
            throw new InvalidArgumentException("Import file not found: {$this->path}");
        }

        return self::format($this->path) === 'csv'
            ? $this->csvRows()
            : $this->spreadsheetRows();
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    private function csvRows(): Generator
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open {$this->path}");
        }

        $columns = null;
        $rowNumber = 0;

        try {
            while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;

                if ($columns === null) {
                    $columns = $this->header($cells);

                    continue;
                }

                $row = $this->mapRow($cells, $columns);

                if ($row === null) {
                    continue;
                }

                $row['__row'] = (string) $rowNumber;

                yield $rowNumber => $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    private function spreadsheetRows(): Generator
    {
        $reader = IOFactory::createReaderForFile($this->path);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($this->path);
        $sheet = $this->sheet !== null && $this->sheet !== ''
            ? $spreadsheet->getSheetByName($this->sheet)
            : $spreadsheet->getActiveSheet();

        if ($sheet === null) {
            throw new InvalidArgumentException("Sheet '{$this->sheet}' was not found in the file.");
        }

        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $columns = null;

        for ($start = 1; $start <= $highestRow; $start += self::CHUNK) {
            $end = min($start + self::CHUNK - 1, $highestRow);
            $rows = $sheet->rangeToArray("A{$start}:{$highestColumn}{$end}", null, true, false, false);

            foreach ($rows as $offset => $cells) {
                $rowNumber = $start + $offset;

                if ($columns === null) {
                    $columns = $this->header($cells);

                    continue;
                }

                $row = $this->mapRow($cells, $columns);

                if ($row === null) {
                    continue;
                }

                $row['__row'] = (string) $rowNumber;

                yield $rowNumber => $row;
            }
        }

        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<int, string>
     */
    private function header(array $cells): array
    {
        $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cells[0] ?? ''));

        return array_map([$this, 'normalizeHeader'], $cells);
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<int, string>  $columns
     * @return array<string, string>|null
     */
    private function mapRow(array $cells, array $columns): ?array
    {
        $row = [];
        $blank = true;

        foreach ($columns as $index => $column) {
            if ($column === '') {
                continue;
            }

            $value = trim((string) ($cells[$index] ?? ''));

            if ($value !== '') {
                $blank = false;
            }

            $row[$column] = $value;
        }

        return $blank ? null : $row;
    }

    private function normalizeHeader(mixed $header): string
    {
        $normalized = strtolower(trim((string) $header));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $normalized), '_');
    }
}
