<?php

namespace App\Http\Controllers\PrintBarcode;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrintBarCodeController extends Controller
{
    public function printBarcode(Request $request)
    {
        $vladimirTagNumber = [];
        //call the result from search function
        $tagNumber = $this->search($request);
        foreach ( $tagNumber->original as $tag) {
            array_push($vladimirTagNumber, $tag->vladimir_tag_number);
        }


        try {
            // Initialize the WindowsPrintConnector with the COM port and baud rate
            $connector = new WindowsPrintConnector("COM2");

            // Create a new Printer object and assign the connector to it
            $printer = new Printer($connector);

            foreach($vladimirTagNumber as $VDM){
                $zplCode = "^XA
                        ~TA000
                        ~JSN
                        ^LT0
                        ^MNW
                        ^MTT
                        ^PON
                        ^PMN
                        ^LH0,0
                        ^JMA
                        ^PR6,6
                        ~SD30
                        ^JUS
                        ^LRN
                        ^CI27
                        ^PA0,1,1,0
                        ^XZ
                        ^XA
                        ^MMT
                        ^PW406
                        ^LL203
                        ^LS0
                        ^BY2,3,41^FT101,130^BCN,,Y,N
                        ^FH\^FD>;$VDM^FS
                        ^FT79,61^A0N,28,28^FH\^CI28^FDVladimir tag Number^FS^CI27
                        ^PQ1,0,1,Y
                        ^XZ";

                $printer->textRaw($zplCode);

                // Cut the paper
                $printer->cut();

                // Close the connection to the printer
                $printer->close();
            }

            return response()->json(
                ['message' => 'ZPL code printed successfully',
                    'data' => $vladimirTagNumber
                ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the printing process
            return response()->json(['message' => 'Error printing ZPL code: ' . $e->getMessage()]);
        }
    }

    public function search(Request $request)
    {
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $status = $request->get('status');
        if ($status == NULL) {
            $status = 1;
        }
        if ($status == "active") {
            $status = 1;
        }
        if ($status == "deactivated") {
            $status = 0;
        }
        if ($status != "active" || $status != "deactivated") {
            $status = 1;
        }

        $fixedAsset = FixedAsset::withTrashed()
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('vladimir_tag_number',  $search );
//                $query->orWhereHas('majorCategory', function ($query) use ($search) {
//                    $query->where('major_category_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('minorCategory', function ($query) use ($search) {
//                    $query->where('minor_category_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('division', function ($query) use ($search) {
//                    $query->where('division_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('location', function ($query) use ($search) {
//                    $query->where('location_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('company', function ($query) use ($search) {
//                    $query->where('company_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('department', function ($query) use ($search) {
//                    $query->where('department_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('accountTitle', function ($query) use ($search) {
//                    $query->where('account_title_name', 'LIKE', '%' . $search . '%');
//                });
            })
            ->orWhereBetween('created_at', [$startDate, $endDate])
            ->orderBy('id', 'ASC');

        //return only the vladimir tag number
        $fixedAsset = $fixedAsset->get(['vladimir_tag_number']);
        return response()->json($fixedAsset);
    }
}
