<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function export(Request $request)
    {
        Gate::authorize('employees.view');

        $filters = $request->only(['search', 'unit', 'status', 'golongan', 'jabatanFungsional', 'aktif']);

        return Excel::download(new EmployeesExport($filters), 'Master-Pegawai-'.now()->format('Y-m-d').'.xlsx');
    }
}
