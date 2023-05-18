<?php

namespace App\Http\Controllers;

use App\Exports\MasterlistExport;
use Illuminate\Http\Request;

class MasterlistExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
        ]);
        $filename = $request->get('filename');
        //ternary if empty the default filename is Fixed_Asset_Date
        $filename = $filename == null ? 'Fixed_Asset'. '_' . date('Y-m-d') :
                    str_replace(' ', '_', $filename) . '_' . date('Y-m-d');
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        return (new MasterlistExport($search, $startDate, $endDate))->download($filename . '.xlsx');
    }
}
