<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Imports\CoaUpdateImport;
use App\Imports\MasterlistImport;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class FixedAssetImportController extends Controller
{
    use ApiResponse;

    public function masterlistImport(Request $request)
    {

//        try {
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
//                            'The file is empty.'
//                        ]
//                    ]
//                ],
//                422
//            );
//        }

            return response()->json(
                [
                    'message' => 'Fixed Asset imported successfully.',
//                'data' => $data
                ],
                200
            );
//        }catch (\Exception $e) {
//            return $e->getMessage() . '-' . $e->getLine() . '-' . $e->getFile();
//            return $this->responseUnprocessable($e->getMessage());
//        }

    }

    public function CoaUpdateImport(Request $request)
    {
//        DB::beginTransaction();
//        try {
            $request->validate([
                'file' => 'required|mimes:csv,txt,xlx,xls,pdf,xlsx'
            ]);

            $file = $request->file('file');
            Excel::import(new CoaUpdateImport, $file);

            //put into an array the data from the Excel file
            $data = Excel::toArray(new CoaUpdateImport, $file);

            /*        if the data is empty collection
                    if (empty($data[0])) {
                        return response()->json(
                            [
                                'message' => 'No data found.',
                                'errors' => [
                                    'file' => [
                                        'The file is empty.'
                                    ]
                                ]
                            ],
                            422
                        );
                    }*/

            return $this->responseSuccess('COA Update imported successfully.', $data);
//        } catch (\Exception $e) {
//            DB::rollBack();
////            return $e->getMessage() . '-' . $e->getLine() . '-' . $e->getFile();
//            return $this->responseUnprocessable($e->getMessage());
//
//        }


    }

}
