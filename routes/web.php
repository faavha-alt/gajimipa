<?php

use App\Http\Controllers\PayslipController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
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
});

require __DIR__.'/auth.php';
