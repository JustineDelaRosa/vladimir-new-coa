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

        //if the data is empty collection
//        if (empty($data[0])) {
//            return response()->json(
//                [
//                    'message' => 'No data found.',
//                    'errors' => [
//                        'file' => [
//                            'Thw file is empty.'
//                        ]
//                    ]
//                ],
//                422
//            );
//        }

        return response()->json(
            [
                'message' => 'Fixed Asset imported successfully.',
                'data' => $data
            ],
            200
        );
    }
}
