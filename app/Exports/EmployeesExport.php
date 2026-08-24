<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export Master Pegawai (CLAUDE.md §11). Mengikuti filter yang sedang
 * aktif di halaman `/master/pegawai` saat tombol Download Excel diklik —
 * bukan selalu semua pegawai. Kolom sengaja sama dgn yang ditampilkan di
 * tabel (tanpa NPWP/No. Rekening/Nama Rekening) — kolom finansial sensitif
 * itu sengaja "hanya terlihat di" form Edit (izin `employees.manage`),
 * tidak pernah ikut daftar/download massal.
 */
class EmployeesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array{search:?string,unit:?string,status:?string,golongan:?string,jabatanFungsional:?string,aktif:?string}  $filters
     */
    public function __construct(private readonly array $filters) {}

    public function collection(): Enumerable
    {
        return Employee::query()
            ->select(['id', 'nip', 'nik', 'nama', 'unit_id', 'employee_status_id', 'golongan_id', 'jabatan_fungsional_id', 'email', 'no_hp', 'id_simpeg', 'status_aktif'])
            ->with(['unit:id,nama_unit', 'employeeStatus:id,nama', 'golongan:id,nama', 'jabatanFungsional:id,nama'])
            ->when($this->filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('nip', 'like', "%{$v}%")
                ->orWhere('nama', 'like', "%{$v}%")
            ))
            ->when($this->filters['unit'] ?? null, fn ($q, $v) => $q->where('unit_id', $v))
            ->when($this->filters['status'] ?? null, fn ($q, $v) => $q->where('employee_status_id', $v))
            ->when($this->filters['golongan'] ?? null, fn ($q, $v) => $q->where('golongan_id', $v))
            ->when($this->filters['jabatanFungsional'] ?? null, fn ($q, $v) => $q->where('jabatan_fungsional_id', $v))
            ->when(($this->filters['aktif'] ?? '') !== '', fn ($q) => $q->where('status_aktif', $this->filters['aktif'] === '1'))
            ->orderBy('nama')
            ->get();
    }

    public function headings(): array
    {
        return ['NIP', 'NIK', 'Nama', 'Unit', 'Status Pegawai', 'Golongan', 'Jabatan Fungsional', 'Email', 'No. HP', 'ID SIMPEG', 'Status Aktif'];
    }

    public function map($employee): array
    {
        return [
            $employee->nip,
            $employee->nik,
            $employee->nama,
            $employee->unit?->nama_unit,
            $employee->employeeStatus?->nama,
            $employee->golongan?->nama,
            $employee->jabatanFungsional?->nama,
            $employee->email,
            $employee->no_hp,
            $employee->id_simpeg,
            $employee->status_aktif ? 'Aktif' : 'Nonaktif',
        ];
    }
}
