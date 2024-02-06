<?php

namespace App\Http\Controllers\Masterlist\PrintBarcode;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
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

        if ($tagNumber === null) {
            return $this->responseUnprocessable('Please select at least one asset');
        }


        $clientIP = request()->ip();

        $printerIP = PrinterIP::where('ip', $clientIP)->first();
        if (!$printerIP || !$printerIP->is_active) {
            //            return response()->json(['message' => 'You are not allowed to print barcode'], 403);
            return $this->responseUnAuthorized('You are not allowed to print barcode');
        }


        if (!$tagNumber) {
            //            return response()->json(['message' => 'No data found'], 404);
            return $this->responseNotFound('No data found');
        }

        try {
            //ZDesigner ZD230-203dpi ZPL
            //ZDesigner GC420t
            $connector = new WindowsPrintConnector("smb://{$printerIP->ip}/ZDesigner ZD230-203dpi ZPL");

            // Create a new Printer object and assign the connector to it
            $printer = new Printer($connector);

            foreach ($tagNumber as $VDM) {
                $fixedAsset = FixedAsset::where('vladimir_tag_number', $VDM['vladimir_tag_number'])->first();
                $AssetRequest = AssetRequest::where('vladimir_tag_number', $VDM['vladimir_tag_number'])->first();
                if ($fixedAsset && $fixedAsset->print_count == 0) {
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
                        $fixedAsset->where('from_request', 1)->update(['can_release' => 1]);
                        $AssetRequest->increment('print_count', 1);
                        $AssetRequest->update(['last_printed' => Carbon::now()]);

                        $fixedAssets = FixedAsset::where('vladimir_tag_number', $VDM['vladimir_tag_number'])->first();
                        $assetRequest = new AssetRequest();
                        if ($fixedAssets && $fixedAsset->from_requests) {
                            activity()
                                ->causedBy(auth('sanctum')->user()) // the user who caused the activity
                                ->performedOn($assetRequest) // the object the activity is performed on
                                ->inLog('Printed') // the log name (should same as in config)
                                ->withProperties(['description' => $fixedAssets->asset_description]) // any properties relevant to the activity
                                ->tap(function ($activity) use ($fixedAssets) {
                                    $activity->subject_id = $fixedAssets->transaction_number;
                                })
                                ->log('Printed'); // a textual description of the activity
                        }

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
                            ^FT3,163^APN,20,6^FB403,1,4,C^FH\^FD" . $VDM['department_name'] . " - " . $VDM['location_name'] . "^FS
                            ^FO24,7^GFA,97,144,12,:Z64:eJxjYCAMfvyo+dn8vKHCAAjPnEk42MzMcKYACM8cg7ANgPDHMaCaxzJg9gck8RtnEOwzPxIOfmZvqAABXHYBACKcLfg=:E7DF
                            ^FT155,142^A0N,21,20^FH\^CI28^FD" . $VDM['asset_description'] . "^FS^CI27
                            ^FO0,0^GFA,705,10556,52,:Z64:eJzt2rFOwlAUBuDe1FBNgJq4aETQxIQV48LGC/gQbG6GUROkNQxsxsQHwEfwDWRjIfIKTSCySUkHSkRqEcLc/088qaRn/3J77j3nNLep5WpYFOaahZrK3xsrCCOuxswf9UHT2MnutUFTH34M31AzG15VcJO2YNNboPtW7/XmqGnkTmAjVQeVkCwEeiExidlEwcdNGDLGxY1BGLND7BuRj2njRify0Woy52M6xL4R+RSIdXJEPow5JMwus9dC58P0D9VzTO0Q/ZMWmlUmU9dCJlfCTVboTA1i9qoYzypmHU0oH6ZGFTMPmGcjept5bzP5MPvGGG3LZpVYL0jlQxidmL3mq8yzZYTOR8rE9NtTYhKTmH9kHNzAQZrsKW7C67OIObYJAxLJfOAgTZKPoLFxoxPrmIQpgCSpHd7AERrDxk14RYUNk89hVcbE+XwYoxzcUL3dIYwtYwwHNxpjqttl4twL+4TJE4aZb8z7lDFUz4Hkd44SBoocYW6V46D3H1cNUKMGkxFq9P5sZKOm/T1G73NGOfOJmlRHwUYv34/RPUjdBbDRvnCjvC6eT7eJm9Z63ybLH198zHhLE62+9ZZGGGYd9b4yfrl0U49ommszObg8j7hOM3hcGW/6fBbRPATZlZmOni4iGnuxMS8eMEtWz1YswsatXU9R4/tj2EQ/040BanRjiO9IP2rLWtM=:E347
                            ^PQ1,0,1,Y
                            ^XZ";
                    if ($fixedAsset) {
                        $fixedAsset->increment('print_count', 1);
                        $fixedAsset->update(['last_printed' => Carbon::now()]);
                        $AssetRequest->increment('print_count', 1);
                        $AssetRequest->update(['last_printed' => Carbon::now()]);
                    }
                }
                $printer->textRaw($zplCode);

                // Cut the paper
                $printer->cut();

                // Close the connection to the printer
                $printer->close();

                // dd($VDM->vladimir_tag_number, $VDM->asset_description);
            }

            /*return response()->json(
                ['message' => 'Barcode printed successfully!',
                    'data' => $tagNumber
                ], 200);*/
            return $this->responseSuccess('Barcode printed successfully!', $tagNumber);
        } catch (Exception $e) {
            // Handle any exceptions that may occur during the printing process
            //            throw new Exception("Couldn't print to this printer: {$e->getMessage()}");

            //            return response()->json(['message' => 'Unable to Print'], 422);
            return $this->responseUnprocessable('Unable to Print, Please contact your support team');
        }
    }

    public function searchPrint(Request $request)
    {
        $vladimirTagNumbers = $request->get('tagNumber');

        $typesOfRequestId = TypeOfRequest::whereIn('type_of_request_name', ['Capex', 'Vehicle'])->pluck('id')->toArray();

        if (empty($vladimirTagNumbers)) {
            return null;
        }

        $fixedAssetQuery = FixedAsset::whereIn('vladimir_tag_number', $vladimirTagNumbers)
            ->whereNotIn('type_of_request_id', array_values($typesOfRequestId));

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
        $filter = $request->get('isRequest');

        /*//type of request capex should not be printed
        $typeOfRequest = TypeOfRequest::where('type_of_request_name', 'Capex')->first()->id;

        // Define the common query for fixed assets
        $fixedAssetQuery = FixedAsset::where('type_of_request_id', '!=', $typeOfRequest); //todo: ask if can be printed now*/

        $typesOfRequestId = TypeOfRequest::whereIn('type_of_request_name', ['Capex', 'Vehicle'])->pluck('id')->toArray();

        $fixedAssetQuery = FixedAsset::whereNotIn('type_of_request_id', array_values($typesOfRequestId))
            ->when($filter == 0 || $filter == null, function ($query) {
                return $query->where(function ($query) {
                    $query->where('from_request', 0)
                        ->orWhere(function ($query) {
                            $query->where('from_request', 1)
                                ->where('is_released', 1);
                        });
                });
            })
            ->when($filter == 1, function ($query) {
                return $query->where('from_request', 1)->where('is_released', 0);
            });

        if ($startDate) {
            $startDate = new DateTime($startDate);
            $fixedAssetQuery->where('created_at', '>=', $startDate->format('Y-m-d H:i:s'));
        }

        if ($endDate) {
            $endDate = new DateTime($endDate);
            $endDate->setTime(23, 59, 59);
            $fixedAssetQuery->where('created_at', '<=', $endDate->format('Y-m-d H:i:s'));
        }
        if ($endDate && $startDate) {
            if (Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
                return $this->responseUnprocessable('Invalid date range');
            }
        }
        $assets = $fixedAssetQuery->orderBy('asset_description', 'ASC')->useFilters()->dynamicPaginate();

        // Return the result array
        $assets->getCollection()->transform(function ($asset) {
            return [
                'id' => $asset->id,
                'requestor_id' => [
                    'id' => $asset->requestor->id ?? '-',
                    'username' => $asset->requestor->username ?? '-',
                    'first_name' => $asset->requestor->first_name ?? '-',
                    'last_name' => $asset->requestor->last_name ?? '-',
                    'employee_id' => $asset->requestor->employee_id ?? '-',
                ],
                'transaction_number' => $asset->transaction_number ?? '-',
                'reference_number' => $asset->reference_number ?? '-',
                'pr_number' => $asset->pr_number ?? '-',
                'po_number' => $asset->po_number ?? '-',
                'rr_number' => $asset->rr_number ?? '-',
                'warehouse_number' => [
                    'id' => $asset->warehouseNumber->id ?? '-',
                    'warehouse_number' => $asset->warehouseNumber->warehouse_number ?? '-',
                ],
                'from_request' => $asset->from_request ?? '-',
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
                'is_released' => $asset->is_released,
                'type_of_request' => [
                    'id' => $asset->typeOfRequest->id ?? '-',
                    'type_of_request_name' => $asset->typeOfRequest->type_of_request_name ?? '-',
                ],
                'asset_specification' => $asset->asset_specification,
                'accountability' => $asset->accountability,
                'accountable' => $asset->accountable,
                'cellphone_number' => $asset->cellphone_number,
                'brand' => $asset->brand ?? '-',
                'supplier' => [
                    'id' => $asset->supplier->id ?? '-',
                    'supplier_code' => $asset->supplier->supplier_code ?? '-',
                    'supplier_name' => $asset->supplier->supplier_name ?? '-',
                ],
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
                'print' => $asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
                'created_at' => $asset->created_at,
            ];
        });
        return $assets;
    }
}
