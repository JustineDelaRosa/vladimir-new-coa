<?php

namespace App\Http\Controllers\Masterlist\PrintBarcode;
use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\PrinterIP;
use App\Models\TypeOfRequest;
use Carbon\Carbon;
use DateTime;
use Essa\APIToolKit\Api\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

//use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;


class PrintBarCodeController extends Controller
{
    use ApiResponse;


//    function getClientName() {
//        $clientIP = $_SERVER['REMOTE_ADDR'];
//        $clientName = gethostbyaddr($clientIP);
//        $clientNameParts = explode('.', $clientName);
//
//        // Returns the computer name without domain
//        return $clientNameParts[0];
//    }

    public function printBarcode(Request $request)
    {
        $tagNumber = $this->searchPrint($request);

        $clientIP = request()->ip();

        $printerIP = PrinterIP::where('ip', $clientIP)->first();
        if (!$printerIP || !$printerIP->is_active) {
            return response()->json(['message' => 'You are not allowed to print barcode'], 403);
        }


        if (!$tagNumber) {
            return response()->json(['message' => 'No data found'], 404);
        }


        try {
            $connector = new WindowsPrintConnector("smb://{$printerIP->ip}/ZDesigner ZD230-203dpi ZPL");

            // Create a new Printer object and assign the connector to it
            $printer = new Printer($connector);

            foreach ($tagNumber as $VDM) {

                $fixedAsset = FixedAsset::where('vladimir_tag_number', $VDM['vladimir_tag_number'])->first();

//                $zplCode = "^XA
//                            ~TA000
//                            ~JSN
//                            ^LT0
//                            ^MNW
//                            ^MTT
//                            ^PON
//                            ^PMN
//                            ^LH0,0
//                            ^JMA
//                            ^PR4,4
//                            ~SD30
//                            ^JUS
//                            ^LRN
//                            ^CI27
//                            ^PA0,1,1,0
//                            ^XZ
//                            ^XA
//                            ^MMT
//                            ^PW406
//                            ^LL203
//                            ^LS0
//                            ^BY3,2,59^FT69,106^BEN,,Y,N
//                            ^FH\^FD" . $VDM['vladimir_tag_number'] . "^FS
//                            ^FT210,45^A0N,17,18^FH\^CI28^FDPROPERTY RECORD^FS^CI27
//                            ^FO93,160^GFA,429,832,32,:Z64:eJzN0TFLw1AQB/BAS9vhMOsTpM3kasClQ83QQifxI8hparo46SLyEEvBUnzFNZ30I/gBLPQReckQcHWsVIuDQyFD+zYvbkLsIoj/9cfxv+MM449SMUWs53puinGmr/txLH3pQ7zSBYTiB1fP2tfC7GV7hYVLOaf5IHs/xsKqvF/lgf21XwDZbnZtLei+brb/08geNLrJDHR/WT7knNsHQRIqy/GcCy4vDWPaW2tc5YobMkI4QeVVX8ZStC30kOGUfDG4aapc8UG+I5yT70yR/A5r5Avy0eBaqdzbhz5D2OfK23LJjya8xhkfkQ8HfaVO87GsIuyh4puptyYOcxgOyTu30dNsOw+SITRRHVtt+mELC6zAsJO6H+16qZcQ6tRveTa59c3d5BV0Cct15C55ED5a1M546r/NJxAcmeQ=:227C
//                            ^FT11,154^APN,20,6^FB395,1,4,C^FH\^FD" . $VDM['asset_description'] . "^FS
//                            ^FO2,1^GFA,793,10452,52,:Z64:eJzt2M9q1EAcB/AJAx1PzqGXHsR5BXtS6bLTN/AVfAyFkqwU3It0z158Eg8TVsxFzCvsksNes+yhEcL8mmxYKYp1vj8hxDa/cz7M/P4l7NJKQBGRE/RwzYRqopRtJP0SJcNUD9Bs6crSmoQtUtILYRCjO6P/ZPbRzYGs6KklR0YVqdcLPUfMsnCNMVmw+UZmDZg6nlr6kJht4epXsPlykSPm008TVAM/iW2TUmO+vskDa33LvAw1NPHWC9Oa03yhw8zJwWSnoTW4ZY6DzUdv62RhiiKTweZdZ5ZFdhlsLr1pjFZFNv+raaM1Sy9OKGt2ATe2yPLGbO42SiC73YVhGM8wFdfc+cTvYRlGMYxgGBqwcffMlAzje5o3jkkYhrM/btwFsWSYTU93ixnm0YBrzTFiwHczA95tzuxw8hnyHHDMmA/PnPVk+srn+5B7Osj/akYzmtHcc4MG1zw5x40Fj+Ka5zOGwUiv+aDBNWM+TIORvTEMoxzjHIaZYmScnX8waLRGz3DDmQNOPs96MkPujzjHjQSPYvd01o8xDMOpAafWLOP6MX3tKWD2nx1wF4yIyseteRFu4sZMwe9p3Zh4/04Mvl5U2XTrsXmTJa07E36O3NF2V2L7Izf0Y7dqzSSUCJW7t9cO64/6LCLUHL0+mPDfJdE82l57zCijCTVHiYGNIAubiBLcVBcKrZssKwmbVQX3R65K3KQDNq6MNrCp5MEkNKf3QaZWB0OUUx5kYp3BxhJuDM1ho0nBRtWo6aJf0/b0CjTBj/8X5gas119Y:227E
//                            ^PQ1,0,1,Y
//                            ^XZ";
                if ($VDM['print_count'] == 0) {
                    //original
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
                                ^BY3,2,53^FT69,92^BEN,,Y,N
                                ^FH\^FD" . $VDM['vladimir_tag_number'] . "^FS
                                ^FT210,34^A0N,17,18^FH\^CI28^FDPROPERTY RECORD^FS^CI27
                                ^FO131,174^GFA,229,320,20,:Z64:eJxjYCAFWHybPKe48SyKmOakTyJOjq4CyGKWk5PnFDkeQxFTnJwk4nQwCUWvwuPkacWNh1DUkQd8nFxsnpzzPjyp+Jya8p1Pjresz/nkPPZLaDuR2Sjl5mVW2DnZmSuxI6dm8amc5+cqG+XK/OwCOycfPGPZVxP76F5K64nIRjE3L72PnZMOuHB2xPgaXQaaF9k8zblbbeacR4ePVPb5YLMXACYPQTo=:7CBE
                                ^FT3,163^APN,20,6^FB403,1,4,C^FH\^FD" . $VDM['department_name'] . " - " . $VDM['location_name'] . "^FS
                                ^FO2,1^GFA,597,10452,52,:Z64:eJzt1z9qwzAUBnAJQbSUOnuhvkKhQwsNVY/Sk9ju0m7tlRw65BoKGbzKmwPGapzQzP4+6EOkfrN/POn9EVgpPGLAvtex/t9GN3E9m4TNBjcq4XmbzWzOpoLNGCImI0xJmJ4wQShPThgj1J8oZOqETSWUpyNMIbQ/UjvnheaaMVK78C1kmLMx87YizDLh/qgLmzcntNvM+8acTapuw4XNgbqw+9wQ5inh+3wm3FPCpP3PNJvZzEbceNygwZorwtyCqUbjCPNQ4eYeI/TZnMdNTuRBg83D1ODS7kPVACNHY4k8GWEcRkTrhgZrUr6PJQyzC4y5Y4zHDfOOpjxvGmT0blcyJicM874pxrwImVfCeNz88S4c2wLOtVPaX49mOd0UB1OM5nm66Q9mwHZBd+Xb9miMn2pMiNtdj+Uxu9juArY/dhPbxo9mNZUo+1Xvmxrrj31UGjUL/2tKP9WYd9M2A/b/s3BZRM1VmcNGRQcbPZSx6UHT9VlTYcaEzsJmGwxs1kGjxlLGC+XBa2Dr7mzivp5q7OZk2rj3E02R46aMH7Bx0cImH3BjO9ScQtSE2NawAYC08bj5ATJ72rI=:9AAB
                                ^FT155,142^A0N,21,20^FH\^CI28^FD" . $VDM['asset_description'] . "^FS^CI27
                                ^FT0,22^APN,20,6^FB161,1,4,C^FH\^FD" . $VDM['accountable'] . "^FS
                                ^PQ1,0,1,Y
                                ^XZ";
                    if ($fixedAsset) {
                        $fixedAsset->increment('print_count', 1);
                        $fixedAsset->update(['last_printed' => Carbon::now()]);
                    }
                } else {
                    //Copy
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
                            ^BY3,2,53^FT69,92^BEN,,Y,N
                            ^FH\^FD" . $VDM['vladimir_tag_number'] . "^FS
                            ^FT210,34^A0N,17,18^FH\^CI28^FDPROPERTY RECORD^FS^CI27
                            ^FO131,174^GFA,229,320,20,:Z64:eJxjYCAFWHybPKe48SyKmOakTyJOjq4CyGKWk5PnFDkeQxFTnJwk4nQwCUWvwuPkacWNh1DUkQd8nFxsnpzzPjyp+Jya8p1Pjresz/nkPPZLaDuR2Sjl5mVW2DnZmSuxI6dm8amc5+cqG+XK/OwCOycfPGPZVxP76F5K64nIRjE3L72PnZMOuHB2xPgaXQaaF9k8zblbbeacR4ePVPb5YLMXACYPQTo=:7CBE
                            ^FT3,163^APN,20,6^FB403,1,4,C^FH\^" . $VDM['department_name'] . " - " . $VDM['location_name'] . "^FS
                            ^FO24,7^GFA,97,144,12,:Z64:eJxjYCAMfvyo+dn8vKHCAAjPnEk42MzMcKYACM8cg7ANgPDHMaCaxzJg9gck8RtnEOwzPxIOfmZvqAABXHYBACKcLfg=:E7DF
                            ^FT155,142^A0N,21,20^FH\^CI28^FD" . $VDM['asset_description'] . "^FS^CI27
                            ^FO0,0^GFA,705,10556,52,:Z64:eJzt2rFOwlAUBuDe1FBNgJq4aETQxIQV48LGC/gQbG6GUROkNQxsxsQHwEfwDWRjIfIKTSCySUkHSkRqEcLc/088qaRn/3J77j3nNLep5WpYFOaahZrK3xsrCCOuxswf9UHT2MnutUFTH34M31AzG15VcJO2YNNboPtW7/XmqGnkTmAjVQeVkCwEeiExidlEwcdNGDLGxY1BGLND7BuRj2njRify0Woy52M6xL4R+RSIdXJEPow5JMwus9dC58P0D9VzTO0Q/ZMWmlUmU9dCJlfCTVboTA1i9qoYzypmHU0oH6ZGFTMPmGcjept5bzP5MPvGGG3LZpVYL0jlQxidmL3mq8yzZYTOR8rE9NtTYhKTmH9kHNzAQZrsKW7C67OIObYJAxLJfOAgTZKPoLFxoxPrmIQpgCSpHd7AERrDxk14RYUNk89hVcbE+XwYoxzcUL3dIYwtYwwHNxpjqttl4twL+4TJE4aZb8z7lDFUz4Hkd44SBoocYW6V46D3H1cNUKMGkxFq9P5sZKOm/T1G73NGOfOJmlRHwUYv34/RPUjdBbDRvnCjvC6eT7eJm9Z63ybLH198zHhLE62+9ZZGGGYd9b4yfrl0U49ommszObg8j7hOM3hcGW/6fBbRPATZlZmOni4iGnuxMS8eMEtWz1YswsatXU9R4/tj2EQ/040BanRjiO9IP2rLWtM=:E347
                            ^PQ1,0,1,Y
                            ^XZ";
                    if ($fixedAsset) {
                        $fixedAsset->increment('print_count', 1);
                        $fixedAsset->update(['last_printed' => Carbon::now()]);
                    }
                }
                $printer->textRaw($zplCode);

                // Cut the paper
                $printer->cut();

                // Close the connection to the printer
                $printer->close();

                // dd($VDM->vladimir_tag_number, $VDM->asset_description);

            }

            return response()->json(
                ['message' => 'Barcode printed successfully!',
                    'data' => $tagNumber
                ], 200);
        } catch (Exception $e) {
            // Handle any exceptions that may occur during the printing process
//            throw new Exception("Couldn't print to this printer: {$e->getMessage()}");

            return response()->json(['message' => 'Unable to Print'], 422);
        }
    }

    public function searchPrint(Request $request)
    {
//        $request->validate([
//            'tagNumber' => 'array|min:1',
//        ],
//            [
//                'tagNumber.required' => 'Please select at least one asset',
//                'tagNumber.array' => 'Please select at least one asset',
//                'tagNumber.min' => 'Please select at least one assets',
//            ]);

        //array of vladimir tag number
        $vladimirTagNumbers = $request->get('tagNumber');

        $typeOfRequestId = TypeOfRequest::where('type_of_request_name', 'Capex')->pluck('id')->first() ?? 0;

        if($vladimirTagNumbers == null){
            //get all vladimir tag number
            $vladimirTagNumbers = FixedAsset::where('type_of_request_id', '!=', $typeOfRequestId)->pluck('vladimir_tag_number')->toArray();
        }

        $fixedAssetQuery = FixedAsset::whereIn('vladimir_tag_number', $vladimirTagNumbers)
            ->where('type_of_request_id', '!=', $typeOfRequestId);

//        $result = [];
        // Chunk the results and populate the result array
        $fixedAssetQuery->chunk(500, function ($assets) use (&$result) {
            foreach ($assets as $asset) {
                $result[] = [
                    'id' => $asset->id,
                    'vladimir_tag_number' => $asset->vladimir_tag_number,
                    'asset_description' => $asset->asset_description,
                    'print_count' => $asset->print_count,
                    'accountable' => $asset->accountable == '-' ? 'Common' : $asset->accountable,
                    'last_printed' => $asset->last_printed,
                    'location_name' => $asset->location->location_name,
                    'department_name' => strlen($asset->department->department_name) > 10 ? $this->acronym($asset->department->department_name) : $asset->department->department_name
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

    public function viewSearchPrint(Request $request)
    {
        //  $id = $request->get('id');
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $limit = $request->get('limit');

        //type of request capex should not be printed
        $typeOfRequest = TypeOfRequest::where('type_of_request_name', 'Capex')->first()->id;

        // Define the common query for fixed assets
        $fixedAssetQuery = FixedAsset::with([
            'formula',
            'majorCategory:id,major_category_name',
            'minorCategory:id,minor_category_name',
        ])
            ->where('type_of_request_id', '!=', $typeOfRequest); //todo: ask if can be printed now

//        if ($startDate && $endDate) {
//            //Ensure the dates are in Y-m-d H:i:s format
//            $startDate = new DateTime($startDate);
//            $endDate = new DateTime($endDate);
//            //set time to an end of day
//            $endDate->setTime(23, 59, 59);
//
//            $fixedAssetQuery->whereBetween('created_at', [$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
//        }



        if ($startDate) {
            $startDate = new DateTime($startDate);
            $fixedAssetQuery->where('created_at', '>=', $startDate->format('Y-m-d H:i:s'));
        }

        if ($endDate) {
            $endDate = new DateTime($endDate);
            $endDate->setTime(23, 59, 59);
            $fixedAssetQuery->where('created_at', '<=', $endDate->format('Y-m-d H:i:s'));
        }
        if($endDate && $startDate) {
            if ($endDate > $startDate) {
                return $this->responseUnprocessable('Start date must be less than end date');
//               return response()->json(['message' => 'Start date must be less than end date'], 422);
            }
        }


        // Add search filter if search is given
        if ($search) {
            $fixedAssetQuery->where(function ($query) use ($search) {
                $query->Where('vladimir_tag_number', '=', $search)
                    ->orWhere('tag_number', 'LIKE', "%$search%")
                    ->orWhere('tag_number_old', 'LIKE', "%$search%")
                    ->orWhere('asset_description', 'LIKE', "%$search%")
                    ->orWhere('accountability', 'LIKE', "%$search%")
                    ->orWhere('accountable', 'LIKE', "%$search%")
                    ->orWhere('brand', 'LIKE', "%$search%")
                    ->orWhere('depreciation_method', 'LIKE', "%$search%");
                $query->orWhereHas('subCapex', function ($query) use ($search) {
                    $query->where('sub_capex', 'LIKE', '%' . $search . '%')
                        ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('minorCategory', function ($query) use ($search) {
                    $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('department.division', function ($query) use ($search) {
                    $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('assetStatus', function ($query) use ($search) {
                    $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('cycleCountStatus', function ($query) use ($search) {
                    $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('depreciationStatus', function ($query) use ($search) {
                    $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('movementStatus', function ($query) use ($search) {
                    $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('location', function ($query) use ($search) {
                    $query->where('location_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('company', function ($query) use ($search) {
                    $query->where('company_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('department', function ($query) use ($search) {
                    $query->where('department_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('accountTitle', function ($query) use ($search) {
                    $query->where('account_title_name', 'LIKE', '%' . $search . '%');
                });
            });

        }
        $assets = $fixedAssetQuery->paginate($limit);


        // Return the result array
        $assets->getCollection()->transform(function ($asset) {
            return [
                'id' => $asset->id,
                'capex' => [
                    'id' => $asset->capex->id ?? '-',
                    'capex' => $asset->capex->capex ?? '-',
                    'project_name' => $asset->capex->project_name ?? '-',
                ],
                'sub_capex' => [
                    'id' => $asset->subCapex->id ?? '-',
                    'sub_capex' => $asset->subCapex->sub_capex ?? '-',
                    'sub_project' => $asset->subCapex->sub_project ?? '-',
                ],
                'vladimir_tag_number' => $asset->vladimir_tag_number,
                'tag_number' => $asset->tag_number,
                'tag_number_old' => $asset->tag_number_old,
                'asset_description' => $asset->asset_description,
                'type_of_request' => [
                    'id' => $asset->typeOfRequest->id ?? '-',
                    'type_of_request_name' => $asset->typeOfRequest->type_of_request_name ?? '-',
                ],
                'asset_specification' => $asset->asset_specification,
                'accountability' => $asset->accountability,
                'accountable' => $asset->accountable,
                'cellphone_number' => $asset->cellphone_number,
                'brand' => $asset->brand ?? '-',
                'division' => [
                    'id' => $asset->department->division->id ?? '-',
                    'division_name' => $asset->department->division->division_name ?? '-',
                ],
                'major_category' => [
                    'id' => $asset->majorCategory->id ?? '-',
                    'major_category_name' => $asset->majorCategory->major_category_name ?? '-',
                ],
                'minor_category' => [
                    'id' => $asset->minorCategory->id ?? '-',
                    'minor_category_name' => $asset->minorCategory->minor_category_name ?? '-',
                ],
                'est_useful_life' => $asset->majorCategory->est_useful_life ?? '-',
                'voucher' => $asset->voucher,
                'receipt' => $asset->receipt,
                'is_additional_cost' => $asset->is_additional_cost,
                'status' => $asset->is_active,
                'quantity' => $asset->quantity,
                'depreciation_method' => $asset->depreciation_method,
                //                    'salvage_value' => $asset->salvage_value,
                'acquisition_date' => $asset->acquisition_date,
                'acquisition_cost' => $asset->acquisition_cost,
                'asset_status' => [
                    'id' => $asset->assetStatus->id ?? '-',
                    'asset_status_name' => $asset->assetStatus->asset_status_name ?? '-',
                ],
                'cycle_count_status' => [
                    'id' => $asset->cycleCountStatus->id ?? '-',
                    'cycle_count_status_name' => $asset->cycleCountStatus->cycle_count_status_name ?? '-',
                ],
                'depreciation_status' => [
                    'id' => $asset->depreciationStatus->id ?? '-',
                    'depreciation_status_name' => $asset->depreciationStatus->depreciation_status_name ?? '-',
                ],
                'movement_status' => [
                    'id' => $asset->movementStatus->id ?? '-',
                    'movement_status_name' => $asset->movementStatus->movement_status_name ?? '-',
                ],
                'care_of' => $asset->care_of,
                'company' => [
                    'id' => $asset->department->company->id ?? '-',
                    'company_code' => $asset->department->company->company_code ?? '-',
                    'company_name' => $asset->department->company->company_name ?? '-',
                ],
                'department' => [
                    'id' => $asset->department->id ?? '-',
                    'department_code' => $asset->department->department_code ?? '-',
                    'department_name' => $asset->department->department_name ?? '-',
                ],
                'charged_department' => [
                    'id' => $additional_cost->department->id ?? '-',
                    'charged_department_code' => $additional_cost->department->department_code ?? '-',
                    'charged_department_name' => $additional_cost->department->department_name ?? '-',
                ],
                'location' => [
                    'id' => $asset->location->id ?? '-',
                    'location_code' => $asset->location->location_code ?? '-',
                    'location_name' => $asset->location->location_name ?? '-',
                ],
                'account_title' => [
                    'id' => $asset->accountTitle->id ?? '-',
                    'account_title_code' => $asset->accountTitle->account_title_code ?? '-',
                    'account_title_name' => $asset->accountTitle->account_title_name ?? '-',
                ],
                'remarks' => $asset->remarks,
                'print_count' => $asset->print_count,
                'last_printed' => $asset->last_printed,
                'created_at' => $asset->created_at,
            ];
        });
        return $assets;
    }

}
