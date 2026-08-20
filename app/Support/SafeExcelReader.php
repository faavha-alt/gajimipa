<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Baca sheet pertama Excel/CSV dengan batas baris & kolom eksplisit.
 *
 * Ditemukan lewat error nyata di server (memory_limit 512M habis, "Allowed
 * memory size... exhausted" di Worksheet::createNewCell): file upload
 * sungguhan punya `<dimension ref="A1:XFD6398"/>` — kolom XFD adalah kolom
 * TERAKHIR yang mungkin di Excel (16.384), jadi PhpSpreadsheet mencoba
 * membentuk objek Cell untuk ~104 juta sel meski data aslinya cuma
 * beberapa ratus baris × puluhan kolom. Ini lazim terjadi pada file yang
 * pernah kena "select all + format" (border/fill diterapkan ke seluruh
 * kolom) — bukan kesalahan data, tapi artefak formatting yang harus
 * ditahan di level pembacaan, bukan diasumsikan tidak akan pernah terjadi.
 *
 * `setReadDataOnly(true)` saja (skip style) TIDAK cukup — sel kosong
 * berformat tetap dibuatkan objek Cell selama masih dalam rentang
 * "dimension" yang dideklarasikan file. Baris/kolom di luar batas di sini
 * jadi tidak pernah dibuat sama sekali (`IReadFilter`), bukan cuma
 * dibuang belakangan.
 */
class SafeExcelReader
{
    private const MAX_ROWS = 5000;

    private const MAX_COLS = 200;

    /**
     * @return array<int,array<int,mixed>>
     */
    public static function readFirstSheet(string $absolutePath): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);

        $maxRow = self::MAX_ROWS;
        $maxCol = self::MAX_COLS;
        $reader->setReadFilter(new class($maxRow, $maxCol) implements IReadFilter
        {
            public function __construct(private readonly int $maxRow, private readonly int $maxCol) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= $this->maxRow && Coordinate::columnIndexFromString($columnAddress) <= $this->maxCol;
            }
        });

        $spreadsheet = $reader->load($absolutePath);
        $rows = $spreadsheet->getSheet(0)->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        return collect($rows)
            ->map(fn ($row) => array_map(fn ($cell) => is_string($cell) ? trim($cell) : $cell, $row))
            ->filter(fn ($row) => collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())
            ->values()
            ->all();
    }
}
