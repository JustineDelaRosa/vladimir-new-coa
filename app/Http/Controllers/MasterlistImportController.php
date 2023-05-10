<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\MasterlistImport;
use App\Models\Division;
use App\Models\MajorCategory;
use Maatwebsite\Excel\Facades\Excel;

class MasterlistImportController extends Controller
{
    public function masterlistImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlx,xls,pdf,xlsx'
        ]);

        $file = $request->file('file');

        Excel::import(new MasterlistImport, $file);
        $file = Excel::toArray(new MasterlistImport, $file);
        return response()->json(
            [
                'message' => 'Masterlist imported successfully.',
                // 'count' => $count,
                'data' => $file,
            ],
            200
        );
    }
}
