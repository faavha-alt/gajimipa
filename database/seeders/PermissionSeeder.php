<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Permission untuk modul yang sudah diimplementasikan (STEP 6: Master Unit,
     * Master Status Pegawai, Master Pegawai). Super Admin tidak perlu di-assign
     * eksplisit — ditangani lewat Gate::before di AppServiceProvider.
     *
     * Referensi hak akses: docs/actors.md §"Ringkasan Matriks" & docs/keputusan-desain.md D3.
     */
    public function run(): void
    {
        $permissions = [
            'units.view',
            'units.manage',
            'employee_statuses.view',
            'employee_statuses.manage',
            'golongans.view',
            'golongans.manage',
            'jabatan_fungsionals.view',
            'jabatan_fungsionals.manage',
            'banks.view',
            'banks.manage',
            'employees.view',
            'employees.manage',
            'periods.view',
            'periods.create',
            'periods.submit',
            'periods.verify',
            'periods.archive',
            'periods.revise',
            'periods.delete',
            'salary_imports.manage',
            'deduction_types.view',
            'deduction_types.manage',
            'deduction_records.view',
            'deduction_records.manage',
            'recurring_deductions.view',
            'recurring_deductions.manage',
            'salary_processing.manage',
            'payslips.manage',
            'deduction_receipts.manage',
            'submission_records.view',
            'submission_records.manage',
            'laporan.view',
            'users.manage',
            'audit_logs.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $matrix = [
            'operator_gaji' => [
                'units.view', 'employee_statuses.view', 'golongans.view', 'jabatan_fungsionals.view', 'banks.view', 'employees.view', 'employees.manage',
                'periods.view', 'periods.create', 'periods.submit', 'periods.archive', 'periods.revise', 'periods.delete',
                'salary_imports.manage', 'deduction_types.view',
                'deduction_records.view', 'deduction_records.manage',
                'recurring_deductions.view', 'recurring_deductions.manage',
                'salary_processing.manage', 'payslips.manage', 'deduction_receipts.manage',
                'submission_records.view', 'submission_records.manage', 'laporan.view',
                'audit_logs.view',
            ],
            'verifikator' => [
                'units.view', 'employee_statuses.view', 'golongans.view', 'jabatan_fungsionals.view', 'banks.view', 'employees.view',
                'periods.view', 'periods.verify', 'periods.archive', 'deduction_types.view',
                'deduction_records.view', 'recurring_deductions.view', 'submission_records.view', 'laporan.view',
            ],
            'pimpinan' => [
                'units.view', 'employee_statuses.view', 'golongans.view', 'jabatan_fungsionals.view', 'banks.view', 'employees.view', 'periods.view',
                'deduction_types.view', 'deduction_records.view', 'recurring_deductions.view', 'submission_records.view', 'laporan.view',
            ],
            // pegawai: tidak ada akses ke master data (§23 CLAUDE.md).
            // users.manage & settings.manage sengaja TIDAK dimasukkan ke role
            // manapun di sini — sesuai docs/actors.md ("User & Hak Akses" &
            // "Pengaturan" cuma "✓" utk Super Admin, "—" utk semua role lain)
            // — cuma bisa diakses lewat Gate::before bypass Super Admin di
            // AppServiceProvider. audit_logs.view BEDA: operator_gaji juga
            // dapat (di atas), tapi scoped ke aktivitas milik sendiri saja
            // (lihat pages.audit-logs.index) — cuma Super Admin lihat semua.
        ];

        foreach ($matrix as $role => $rolePermissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
                ->syncPermissions($rolePermissions);
        }
    }
}
