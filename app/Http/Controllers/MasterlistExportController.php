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
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        return (new MasterlistExport($search, $startDate, $endDate))->download('masterlist.xlsx');
    }
}
