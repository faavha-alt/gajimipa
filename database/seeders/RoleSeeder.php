<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Role dasar sesuai docs/actors.md. Permission granular per modul
     * ditambahkan bertahap di STEP selanjutnya saat modul terkait dibangun.
     */
    public function run(): void
    {
        foreach (['super_admin', 'operator_gaji', 'verifikator', 'pimpinan', 'pegawai'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
