<?php

namespace App\Http\Controllers\PrintBarcode;
//require __DIR__ . '/../../../../vendor/autoload.php';
use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\PrinterIP;
use Exception;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
//use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;


class PrintBarCodeController extends Controller
{
    public function printBarcode(Request $request)
    {
        $tagNumber = $this->search($request);
         $clientIP = $request->ip();
//        //accept only the ip address with 10.10.x.x
//        if (substr($clientIP, 0, 7) === "10.10.1") {
//            // print the barcode
//            return $this->print($tagNumber);
//        } else {
//            return response()->json(['message' => 'You are not allowed to print barcode'], 403);
//        }
//        return $tagNumber;
        if (!$tagNumber) {
            return response()->json(['message' => 'No data found'], 404);
        }

        $printerIP = PrinterIP::where('ip' , $clientIP)->first();
        //check status on printerIP table
        if (!$printerIP->is_active) {
            return response()->json(['message' => 'You are not allowed to print barcode'], 403);
        }



        try {
            //get the ip from ative printerIP table in a database
//$printerIP = PrinterIP::where('is_active', true)->first()->ip;
            // Initialize the WindowsPrintConnector with the COM port and baud rate
            //$connector = new WindowsPrintConnector("COM1");
            // $connector = new NetworkPrintConnector("10.10.10.11" , 8000);
//$connector = new WindowsPrintConnector("ZDesigner ZD230-203dpi ZPL");
            //$printer = '\\\\10.10.10.11\\ZDesigner ZD230-203dpi ZPL';
            $connector = new WindowsPrintConnector("smb://{$printerIP->ip}/ZDesigner ZD230-203dpi ZPL");
            //check if the smb://10.10.10.11 is available

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
                            ^FH\^FD".$VDM['vladimir_tag_number']."^FS
                            ^FT210,45^A0N,17,18^FH\^CI28^FDPROPERTY RECORD^FS^CI27
                            ^FO93,160^GFA,429,832,32,:Z64:eJzN0TFLw1AQB/BAS9vhMOsTpM3kasClQ83QQifxI8hparo46SLyEEvBUnzFNZ30I/gBLPQReckQcHWsVIuDQyFD+zYvbkLsIoj/9cfxv+MM449SMUWs53puinGmr/txLH3pQ7zSBYTiB1fP2tfC7GV7hYVLOaf5IHs/xsKqvF/lgf21XwDZbnZtLei+brb/08geNLrJDHR/WT7knNsHQRIqy/GcCy4vDWPaW2tc5YobMkI4QeVVX8ZStC30kOGUfDG4aapc8UG+I5yT70yR/A5r5Avy0eBaqdzbhz5D2OfK23LJjya8xhkfkQ8HfaVO87GsIuyh4puptyYOcxgOyTu30dNsOw+SITRRHVtt+mELC6zAsJO6H+16qZcQ6tRveTa59c3d5BV0Cct15C55ED5a1M546r/NJxAcmeQ=:227C
                            ^FT11,154^APN,20,6^FB395,1,4,C^FH\^FD".$VDM['asset_description']."^FS
                            ^FO2,1^GFA,793,10452,52,:Z64:eJzt2M9q1EAcB/AJAx1PzqGXHsR5BXtS6bLTN/AVfAyFkqwU3It0z158Eg8TVsxFzCvsksNes+yhEcL8mmxYKYp1vj8hxDa/cz7M/P4l7NJKQBGRE/RwzYRqopRtJP0SJcNUD9Bs6crSmoQtUtILYRCjO6P/ZPbRzYGs6KklR0YVqdcLPUfMsnCNMVmw+UZmDZg6nlr6kJht4epXsPlykSPm008TVAM/iW2TUmO+vskDa33LvAw1NPHWC9Oa03yhw8zJwWSnoTW4ZY6DzUdv62RhiiKTweZdZ5ZFdhlsLr1pjFZFNv+raaM1Sy9OKGt2ATe2yPLGbO42SiC73YVhGM8wFdfc+cTvYRlGMYxgGBqwcffMlAzje5o3jkkYhrM/btwFsWSYTU93ixnm0YBrzTFiwHczA95tzuxw8hnyHHDMmA/PnPVk+srn+5B7Osj/akYzmtHcc4MG1zw5x40Fj+Ka5zOGwUiv+aDBNWM+TIORvTEMoxzjHIaZYmScnX8waLRGz3DDmQNOPs96MkPujzjHjQSPYvd01o8xDMOpAafWLOP6MX3tKWD2nx1wF4yIyseteRFu4sZMwe9p3Zh4/04Mvl5U2XTrsXmTJa07E36O3NF2V2L7Izf0Y7dqzSSUCJW7t9cO64/6LCLUHL0+mPDfJdE82l57zCijCTVHiYGNIAubiBLcVBcKrZssKwmbVQX3R65K3KQDNq6MNrCp5MEkNKf3QaZWB0OUUx5kYp3BxhJuDM1ho0nBRtWo6aJf0/b0CjTBj/8X5gas119Y:227E
                            ^PQ1,0,1,Y
                            ^XZ";
                $printer->textRaw($zplCode);

                // Cut the paper
                $printer->cut();

                // Close the connection to the printer
                $printer->close();

                // dd($VDM->vladimir_tag_number, $VDM->asset_description);

            }

            return response()->json(
                ['message' => 'ZPL code printed successfully!',
                    'data' => $tagNumber
                ], 200);
        } catch (Exception $e) {
            // Handle any exceptions that may occur during the printing process
            return response()->json(['message' => 'Error printing ZPL code: ' . $e->getMessage()]);
        }
    }

    public function search(Request $request)
    {
        //  $id = $request->get('id');
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $faStatus = $request->get('faStatus');

        // Simplify the logic for faStatus
        if ($faStatus == null) {
            $faStatus = ['Good', 'For Disposal', 'For Repair', 'Spare', 'Sold', 'Write Off', 'Disposed'];
        } else if ($faStatus == 'Disposed, Sold') {
            $faStatus = ['Disposed', 'Sold'];
        } else if ($faStatus == 'Disposed' || $faStatus == 'Sold') {
            $faStatus = [$faStatus];
        } else {
            $faStatus = array_filter(array_map('trim', explode(',', $faStatus)), function ($status) {
                return $status !== 'Disposed';
            });
        }

        // Define the common query for fixed assets
        $fixedAssetQuery = FixedAsset::with([
            'formula',
            'division:id,division_name',
            'majorCategory:id,major_category_name',
            'minorCategory:id,minor_category_name',
        ])
            ->where('type_of_request_id', '!=', 2)
            ->whereIn('faStatus', $faStatus);

        // Add date filter if both startDate and endDate are given
        if ($startDate && $endDate){
            $fixedAssetQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Add search filter if search is given
        if ($search) {
            $fixedAssetQuery->where(function ($query) use ($search) {
                $query->where('project_name', 'LIKE', "%$search%")
                    ->orWhere('vladimir_tag_number', 'LIKE', "%$search%")
                    ->orWhere('tag_number', 'LIKE', "%$search%")
                    ->orWhere('tag_number_old', 'LIKE', "%$search%")
                    ->orWhere('accountability', 'LIKE', "%$search%")
                    ->orWhere('accountable', 'LIKE', "%$search%")
                    ->orWhere('faStatus', 'LIKE', "%$search%")
                    ->orWhere('brand', 'LIKE', "%$search%")
                    ->orWhere('depreciation_method', 'LIKE', "%$search%");
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->where('major_category_name', 'LIKE', "%$search%");
                });
                $query->orWhereHas('minorCategory', function ($query) use ($search) {
                    $query->where('minor_category_name', 'LIKE', "%$search%");
                });
                $query->orWhereHas('division', function ($query) use ($search) {
                    $query->where('division_name', 'LIKE', "%$search%");
                });
                $query->orWhereHas('location', function ($query) use ($search) {
                    $query->where('location_name', 'LIKE', "%$search%");
                });
            });
        }

        // Chunk the results and populate the result array
        $fixedAssetQuery->chunk(500, function ($assets) use (&$result) {
            foreach ($assets as $asset) {
                $result[] = [
                    'id' => $asset->id,
                    'vladimir_tag_number' => $asset->vladimir_tag_number,
                    'asset_description' => $asset->asset_description,
                    'location_name' => $asset->location_name,
                    //if the department has 10 characters or more, then make it an acronym
                    'department_name' => strlen($asset->department_name) > 10 ? $this->acronym($asset->department_name) : $asset->department_name
                ];
            }
        });

        // Return the result array
        return $result;
    }

    private function acronym($department_name): string
    {
        $words = explode(" ", $department_name);
        $acronym = "";
        foreach ($words as $word) {
            $word = preg_replace('/[^A-Za-z0-9\-]/', '', $word);
            if (strtoupper($word) == $word) {
                $acronym .= $word;
            } else {
                $word = trim($word);
                if ($word !== "and" && $word !== "of" && $word !== "the") {
                    $acronym .= $word[0];
                }
            }
        }
        return $acronym;
    }
}
