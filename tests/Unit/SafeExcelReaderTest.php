<?php

namespace Tests\Unit;

use App\Support\SafeExcelReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/**
 * Regresi untuk bug nyata di server: file dengan "dimension" raksasa (mis.
 * A1:XFD6398 — kolom XFD adalah kolom terakhir yang mungkin di Excel)
 * gara-gara formatting diterapkan ke seluruh kolom, bikin PhpSpreadsheet
 * coba membentuk objek Cell untuk puluhan/ratusan juta sel kosong sampai
 * memory_limit 512M habis. Lihat App\Support\SafeExcelReader.
 */
class SafeExcelReaderTest extends TestCase
{
    public function test_reads_real_data_and_ignores_cells_far_outside_bounds(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValueExplicit('A1', 'NIP', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B1', 'Nama', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A2', '123456', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B2', 'Pegawai Uji', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // Sel jauh di luar data asli — analog kolom/baris "hantu" yang
        // menyebabkan OOM nyata di server (kolom IV = 256, baris 6000,
        // keduanya di luar batas SafeExcelReader: MAX_COLS=200, MAX_ROWS=5000).
        $sheet->setCellValueExplicit('IV6000', 'x', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $path = tempnam(sys_get_temp_dir(), 'safe_excel_reader_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $rows = SafeExcelReader::readFirstSheet($path);

        unlink($path);

        $this->assertCount(2, $rows);
        $this->assertSame('NIP', $rows[0][0]);
        $this->assertSame('Nama', $rows[0][1]);
        $this->assertSame('123456', $rows[1][0]);
        $this->assertSame('Pegawai Uji', $rows[1][1]);
    }
}
