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
            'employees.view',
            'employees.manage',
            'periods.view',
            'periods.create',
            'periods.submit',
            'periods.verify',
            'periods.archive',
            'periods.revise',
            'salary_imports.manage',
            'deduction_types.view',
            'deduction_types.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $matrix = [
            'operator_gaji' => [
                'units.view', 'employee_statuses.view', 'employees.view', 'employees.manage',
                'periods.view', 'periods.create', 'periods.submit', 'periods.archive', 'periods.revise',
                'salary_imports.manage', 'deduction_types.view',
            ],
            'verifikator' => [
                'units.view', 'employee_statuses.view', 'employees.view',
                'periods.view', 'periods.verify', 'periods.archive', 'deduction_types.view',
            ],
            'pimpinan' => ['units.view', 'employee_statuses.view', 'employees.view', 'periods.view', 'deduction_types.view'],
            // pegawai: tidak ada akses ke master data (§23 CLAUDE.md).
        ];

        foreach ($matrix as $role => $rolePermissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
                ->syncPermissions($rolePermissions);
        }
    }
}
