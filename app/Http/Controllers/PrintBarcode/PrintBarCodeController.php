<?php

namespace App\Http\Controllers\PrintBarcode;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrintBarCodeController extends Controller
{
    public function printBarcode(Request $request)
    {
        $tagNumber = $this->search($request);
        if ($tagNumber == "[]") {
            return response()->json(['message' => 'No data found'], 404);
        }


        try {
            // Initialize the WindowsPrintConnector with the COM port and baud rate
            $connector = new WindowsPrintConnector("COM2");

            // Create a new Printer object and assign the connector to it
            $printer = new Printer($connector);

            foreach($tagNumber as $VDM) {


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
                                ^PR4,4
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
                                ^BY3,2,59^FT69,106^BEN,,Y,N
                                ^FH\^FD$VDM->vladimir_tag_number^FS
                                ^FT210,45^A0N,17,18^FH\^CI28^FDPROPERTY RECORD^FS^CI27
                                ^FO93,160^GFA,429,832,32,:Z64:eJzN0TFLw1AQB/BAS9vhMOsTpM3kasClQ83QQifxI8hparo46SLyEEvBUnzFNZ30I/gBLPQReckQcHWsVIuDQyFD+zYvbkLsIoj/9cfxv+MM449SMUWs53puinGmr/txLH3pQ7zSBYTiB1fP2tfC7GV7hYVLOaf5IHs/xsKqvF/lgf21XwDZbnZtLei+brb/08geNLrJDHR/WT7knNsHQRIqy/GcCy4vDWPaW2tc5YobMkI4QeVVX8ZStC30kOGUfDG4aapc8UG+I5yT70yR/A5r5Avy0eBaqdzbhz5D2OfK23LJjya8xhkfkQ8HfaVO87GsIuyh4puptyYOcxgOyTu30dNsOw+SITRRHVtt+mELC6zAsJO6H+16qZcQ6tRveTa59c3d5BV0Cct15C55ED5a1M546r/NJxAcmeQ=:227C
                                ^FT11,154^APN,20,6^FB395,1,4,C^FH\^FD$VDM->asset_description^FS
                                ^FO2,1^GFA,793,10452,52,:Z64:eJzt2M9q1EAcB/AJAx1PzqGXHsR5BXtS6bLTN/AVfAyFkqwU3It0z158Eg8TVsxFzCvsksNes+yhEcL8mmxYKYp1vj8hxDa/cz7M/P4l7NJKQBGRE/RwzYRqopRtJP0SJcNUD9Bs6crSmoQtUtILYRCjO6P/ZPbRzYGs6KklR0YVqdcLPUfMsnCNMVmw+UZmDZg6nlr6kJht4epXsPlykSPm008TVAM/iW2TUmO+vskDa33LvAw1NPHWC9Oa03yhw8zJwWSnoTW4ZY6DzUdv62RhiiKTweZdZ5ZFdhlsLr1pjFZFNv+raaM1Sy9OKGt2ATe2yPLGbO42SiC73YVhGM8wFdfc+cTvYRlGMYxgGBqwcffMlAzje5o3jkkYhrM/btwFsWSYTU93ixnm0YBrzTFiwHczA95tzuxw8hnyHHDMmA/PnPVk+srn+5B7Osj/akYzmtHcc4MG1zw5x40Fj+Ka5zOGwUiv+aDBNWM+TIORvTEMoxzjHIaZYmScnX8waLRGz3DDmQNOPs96MkPujzjHjQSPYvd01o8xDMOpAafWLOP6MX3tKWD2nx1wF4yIyseteRFu4sZMwe9p3Zh4/04Mvl5U2XTrsXmTJa07E36O3NF2V2L7Izf0Y7dqzSSUCJW7t9cO64/6LCLUHL0+mPDfJdE82l57zCijCTVHiYGNIAubiBLcVBcKrZssKwmbVQX3R65K3KQDNq6MNrCp5MEkNKf3QaZWB0OUUx5kYp3BxhJuDM1ho0nBRtWo6aJf0/b0CjTBj/8X5gas119Y:227E
                                ^PQ1,0,1,Y
                                ^XZ";
                    $printer->textRaw($zplCode);

                    // Cut the paper
                    $printer->cut();

                    // Close the connection to the printer
                    $printer->close();

//                    dd($VDM->vladimir_tag_number, $VDM->asset_description);

            }

            return response()->json(
                ['message' => 'ZPL code printed successfully',
                    'data' => $tagNumber
                ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the printing process
            return response()->json(['message' => 'Error printing ZPL code: ' . $e->getMessage()]);
        }
    }

    public function search(Request $request)
    {
//      //  $id = $request->get('id');
        $tagNumber = $request->get('tagNumber');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

//        $oldAsset = $request->get('oldAsset');
//        $fixedAsset = FixedAsset::withTrashed()->
//            where('capex','-')->
//            where(function ($query) use ($tagNumber, $startDate, $endDate)  {
//                $query->where('vladimir_tag_number', $tagNumber)
//                        ->orWhere('type_of_request',$tagNumber)
//                        ->orWhere('accountability',$tagNumber)
//                        ->orWhere('accountable', $tagNumber)
//                        ->orWhere('is_old_asset', $tagNumber)
//                        ->orWhereBetween('created_at', [$startDate, $endDate]);
//                $query->orWhereHas('majorCategory', function ($query) use ($tagNumber) {
//                    $query->where('major_category_name', $tagNumber);
//                });
//                $query->orWhereHas('minorCategory', function ($query) use ($tagNumber) {
//                    $query->where('minor_category_name', $tagNumber);
//                });
//                $query->orWhereHas('division', function ($query) use ($tagNumber) {
//                    $query->where('division_name', $tagNumber);
//                });
//                $query->orWhereHas('location', function ($query) use ($tagNumber) {
//                    $query->where('location_name',  $tagNumber);
//                });
//                $query->orWhereHas('company', function ($query) use ($tagNumber) {
//                    $query->where('company_name', $tagNumber);
//                });
//                $query->orWhereHas('department', function ($query) use ($tagNumber) {
//                    $query->where('department_name', $tagNumber);
//                });
//                $query->orWhereHas('accountTitle', function ($query) use ($tagNumber) {
//                    $query->where('account_title_name', $tagNumber);
//                });
//            })
//            ->orderBy('id', 'ASC');

        $fixedAsset = FixedAsset::withTrashed()
            ->where('capex', '-')
            ->where(function ($query) use ($tagNumber, $startDate, $endDate) {
                $query->Where('vladimir_tag_number', $tagNumber )
                        ->orWhere('tag_number',$tagNumber)
                        ->orWhere('tag_number_old',$tagNumber)
//                    ->orWhere('type_of_request',$tagNumber)
//                    ->orWhere('accountability',$tagNumber)
//                    ->orWhere('accountable',$tagNumber)
//                    ->orWhere('depreciation_method',$tagNumber)
                    ->orWhereBetween('created_at', [$startDate, $endDate]);
//                $query->orWhereHas('majorCategory', function ($query) use ($tagNumber) {
//                    //include soft deleted major category
//                    $query->withTrashed()->where('major_category_name', $tagNumber );
//                });
//                $query->orWhereHas('minorCategory', function ($query) use ($tagNumber) {
//                    $query->withTrashed()->where('minor_category_name', $tagNumber);
//                });
//                $query->orWhereHas('division', function ($query) use ($tagNumber) {
//                    $query->withTrashed()->where('division_name',$tagNumber);
//                });
//                $query->orWhereHas('location', function ($query) use ($tagNumber) {
//                    $query->where('location_name', $tagNumber);
//                });
//                $query->orWhereHas('company', function ($query) use ($tagNumber) {
//                    $query->where('company_name', $tagNumber);
//                });
//                $query->orWhereHas('department', function ($query) use ($tagNumber) {
//                    $query->where('department_name', $tagNumber );
//                });
//                $query->orWhereHas('accountTitle', function ($query) use ($tagNumber) {
//                    $query->where('account_title_name', $tagNumber );
//                });
            })
            ->orderBy('id', 'ASC');

        //return only the vladimir tag number
        $fixedAsset = $fixedAsset->get([
                'vladimir_tag_number',
                //'department_name'
                'asset_description',
            ]);
        return $fixedAsset;
    }
}
