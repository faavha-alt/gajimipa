<?php

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
});

require __DIR__.'/auth.php';
