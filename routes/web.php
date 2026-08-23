<?php

use App\Http\Controllers\DeductionReceiptController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\RekapSetoranController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'active'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', 'active'])
    ->name('profile');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Volt::route('master/unit', 'pages.units.index')
        ->middleware('permission:units.view')
        ->name('units.index');

    Volt::route('master/status-pegawai', 'pages.employee-statuses.index')
        ->middleware('permission:employee_statuses.view')
        ->name('employee-statuses.index');

    Volt::route('master/golongan', 'pages.golongans.index')
        ->middleware('permission:golongans.view')
        ->name('golongans.index');

    Volt::route('master/jabatan-fungsional', 'pages.jabatan-fungsionals.index')
        ->middleware('permission:jabatan_fungsionals.view')
        ->name('jabatan-fungsionals.index');

    Volt::route('master/bank', 'pages.banks.index')
        ->middleware('permission:banks.view')
        ->name('banks.index');

    Volt::route('master/pegawai', 'pages.employees.index')
        ->middleware('permission:employees.view')
        ->name('employees.index');

    Volt::route('master/pegawai/import', 'pages.employees.import')
        ->middleware('permission:employees.manage')
        ->name('employees.import');

    Volt::route('periode-gaji', 'pages.salary-periods.index')
        ->middleware('permission:periods.view')
        ->name('salary-periods.index');

    Volt::route('periode-gaji/{period}', 'pages.salary-periods.show')
        ->middleware('permission:periods.view')
        ->name('salary-periods.show');

    Volt::route('import/gaji-pusat', 'pages.salary-imports.create')
        ->middleware('permission:salary_imports.manage')
        ->name('salary-imports.create');

    Volt::route('master/jenis-potongan', 'pages.deduction-types.index')
        ->middleware('permission:deduction_types.view')
        ->name('deduction-types.index');

    Volt::route('data-potongan', 'pages.deduction-records.index')
        ->middleware('permission:deduction_records.view')
        ->name('deduction-records.index');

    Volt::route('data-potongan/import', 'pages.deduction-records.import')
        ->middleware('permission:deduction_records.manage')
        ->name('deduction-records.import');

    Volt::route('proses-gaji', 'pages.salary-processing.create')
        ->middleware('permission:salary_processing.manage')
        ->name('salary-processing.create');

    Volt::route('periode-gaji/{period}/pegawai', 'pages.salary-records.index')
        ->middleware('permission:periods.view')
        ->name('salary-records.index');

    Volt::route('periode-gaji/{period}/pegawai/{salaryRecord}', 'pages.salary-records.show')
        ->middleware('permission:periods.view')
        ->name('salary-records.show');

    Volt::route('periode-gaji/{period}/slip-gaji', 'pages.payslips.index')
        ->middleware('permission:payslips.manage')
        ->name('payslips.index');

    Volt::route('slip-saya', 'pages.payslips.mine')
        ->name('payslips.mine');

    Route::get('slip-gaji/{payslip}/preview', [PayslipController::class, 'preview'])->name('payslips.preview');
    Route::get('slip-gaji/{payslip}/download', [PayslipController::class, 'download'])->name('payslips.download');

    Volt::route('periode-gaji/{period}/bukti-potongan', 'pages.deduction-receipts.index')
        ->middleware('permission:deduction_receipts.manage')
        ->name('deduction-receipts.index');

    Volt::route('bukti-potongan-saya', 'pages.deduction-receipts.mine')
        ->name('deduction-receipts.mine');

    Route::get('bukti-potongan/{deductionReceipt}/preview', [DeductionReceiptController::class, 'preview'])->name('deduction-receipts.preview');
    Route::get('bukti-potongan/{deductionReceipt}/download', [DeductionReceiptController::class, 'download'])->name('deduction-receipts.download');

    Volt::route('periode-gaji/{period}/rekap-setoran', 'pages.rekap-setoran.index')
        ->middleware('permission:submission_records.view')
        ->name('rekap-setoran.index');

    Route::get('rekap-setoran/{period}/jenis/pdf', [RekapSetoranController::class, 'jenisPdf'])->name('rekap-setoran.jenis-pdf');
    Route::get('rekap-setoran/{period}/jenis/excel', [RekapSetoranController::class, 'jenisExcel'])->name('rekap-setoran.jenis-excel');
    Route::get('rekap-setoran/{period}/bank/pdf', [RekapSetoranController::class, 'bankPdf'])->name('rekap-setoran.bank-pdf');
    Route::get('rekap-setoran/{period}/bank/excel', [RekapSetoranController::class, 'bankExcel'])->name('rekap-setoran.bank-excel');

    Volt::route('laporan', 'pages.laporan.index')
        ->middleware('permission:laporan.view')
        ->name('laporan.index');

    Volt::route('periode-gaji/{period}/laporan-bulanan', 'pages.laporan.bulanan')
        ->middleware('permission:laporan.view')
        ->name('laporan.bulanan');

    Volt::route('laporan/tahunan/{tahun}', 'pages.laporan.tahunan')
        ->middleware('permission:laporan.view')
        ->name('laporan.tahunan');

    Route::get('laporan/{period}/bulanan/pdf', [LaporanController::class, 'bulananPdf'])->name('laporan.bulanan-pdf');
    Route::get('laporan/{period}/bulanan/excel', [LaporanController::class, 'bulananExcel'])->name('laporan.bulanan-excel');
    Route::get('laporan/tahunan/{tahun}/pdf', [LaporanController::class, 'tahunanPdf'])->name('laporan.tahunan-pdf');
    Route::get('laporan/tahunan/{tahun}/excel', [LaporanController::class, 'tahunanExcel'])->name('laporan.tahunan-excel');

    Volt::route('pengguna', 'pages.users.index')
        ->middleware('permission:users.manage')
        ->name('users.index');
});

require __DIR__.'/auth.php';
