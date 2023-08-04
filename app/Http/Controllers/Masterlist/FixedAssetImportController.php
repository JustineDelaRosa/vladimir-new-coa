<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Imports\MasterlistImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class FixedAssetImportController extends Controller
{
    public function masterlistImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlx,xls,pdf,xlsx'
        ]);

        $file = $request->file('file');

        Excel::import(new MasterlistImport, $file);

        //put into an array the data from the Excel file
        $data = Excel::toArray(new MasterlistImport, $file);
        return response()->json(
            [
                'message' => 'Fixed Asset imported successfully.',
                'data' => $data
            ],
            200
        );
    }
}
