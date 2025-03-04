<?php

namespace App\Http\Controllers\API;


use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\YmirPRItem;
use App\Models\YmirPRTransaction;
use App\Traits\AddingPRHandler;
use App\Traits\RequestShowDataHandler;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\AssetRequestHandler;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\File\File;
use function PHPUnit\Framework\isEmpty;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\AddingPr\CreateAddingPrRequest;
use App\Http\Requests\AddingPr\UpdateAddingPrRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddingPrController extends Controller
{
    use ApiResponse, AssetRequestHandler, AddingPRHandler, RequestShowDataHandler;

    public function index(Request $request)
    {
        $toPr = $request->get('toPr', null);
        $perPage = $request->input('per_page', null);

        $assetRequest = AssetRequest::where('status', 'Approved')
            ->whereNull('deleted_at')
            ->when($toPr !== null, function ($query) use ($toPr) {
                return $query->where($toPr == 0 ? 'pr_number' : 'pr_number', $toPr == 0 ? '!=' : '=', null);
            })
            ->useFilters()
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('transaction_number')
            ->map(function ($assetRequestCollection) {
                $assetRequest = $assetRequestCollection->first();
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                return $this->transformIndexAssetRequest($assetRequest);
            })
            ->values();

        if ($perPage !== null) {
            $assetRequest->paginate($perPage);
        }

        return $assetRequest;
    }

    public function store(CreateAddingPrRequest $request): JsonResponse
    {
        $assetRequest = AssetRequest::create($request->all());

        return $this->responseCreated('AssetRequest created successfully', $assetRequest);
    }

    public function show(Request $request, $transactionNumber)
    {
        $requiredRole = array_map('strtolower', ['Purchase Request', 'Admin', 'Super Admin']);
        $userRole = strtolower(auth('sanctum')->user()->role->role_name);

        if (in_array($userRole, $requiredRole)) {
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->dynamicPaginate();
        } else {
            return $this->responseUnprocessable('You are not allowed to view this transaction.');
        }
        $assetRequest = $this->responseData($assetRequest);

        if ($assetRequest->isEmpty()) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        return $assetRequest;
    }

    public function update(UpdateAddingPrRequest $request, $transactionNumber): JsonResponse
    {
        $prNumber = $request->pr_number;
//        $businessUnitId = $request->business_unit_id;
        $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('status', 'Approved')
            ->get();
        if ($assetRequests->isEmpty()) {
            return $this->responseUnprocessable('Asset Request is not yet approved');
        }
        $assetRequests->each(function ($assetRequest) use ($prNumber) {
            $assetRequest->update([
                'pr_number' => $prNumber,
//                'business_unit_id' => $businessUnitId,
                'filter' => 'To PO'
            ]);
        });
        $this->activityLog($assetRequests, $prNumber);

        return $this->responseSuccess('PR No. added successfully');
    }

    public function destroy(AssetRequest $assetRequest): JsonResponse
    {
        $assetRequest->delete();

        return $this->responseDeleted();
    }

    public function removePR($transactionNumber): JsonResponse
    {
        $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('status', 'Approved')
            ->where('po_number', null)
            ->get();
        if ($assetRequests->isEmpty()) {
            return $this->responseUnprocessable('You cannot remove PR No. already has PO No.');
        }
        $assetRequests->each(function ($assetRequest) {
            $assetRequest->update([
                'pr_number' => null,
                'business_unit_id' => null,
            ]);
        });
        $this->activityLog($assetRequests, null);

        return $this->responseSuccess('PR No. removed successfully');
    }


    public function requestToPR(Request $request)
    {
        try {
            DB::beginTransaction();

            $transactionNumber = $request->input('transaction_number', null);
            $perPage = $request->input('per_page', null);
            $pagination = $request->input('pagination', null);
            $prNumber = (new \App\Models\AssetRequest)->generatePRNumber();

//            return $prNumber;

            /*$user = auth('sanctum')->user();
            $firstname = $user->firstname;
            $lastname = $user->lastname;
            $employee_id = $user->employee_id;*/

//            return AssetRequest::where('transaction_number', $transactionNumber)->first()->is_pr_returned;

            $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
                ->where('status', 'Approved')
                ->where('is_fa_approved', 1)
                ->useFilters()
                ->orderBy('created_at', 'desc')
                ->get()
                ->each(function ($assetRequest) use ($prNumber) {
                    if (is_null($assetRequest->pr_number)) {
                        $assetRequest->update([
                            'pr_number' => $prNumber,
                        ]);
                    }
                });

            $filteredAndGroupedAssetRequests = $assetRequests->fresh()
                ->where('status', 'Approved')
                ->where('is_fa_approved', true)
                ->whereNull('deleted_at')
                ->groupBy('transaction_number')
                ->map(function ($assetRequestCollection) use ($transactionNumber) {
                    $latestDateNeeded = $assetRequestCollection->max('date_needed');
                    $assetRequest = $assetRequestCollection->first();
                    $assetRequest->date_needed = $latestDateNeeded;
                    $listOfItems = $assetRequestCollection->map(function ($item) use ($transactionNumber) {
                        return [
                            'id' => AssetRequest::where('transaction_number', $transactionNumber)->first()->is_pr_returned == 1 ? $item->ymir_id : $item->id,
                            'reference_no' => $item->reference_number,
                            'asset_description' => $item->asset_description . "-" . $item->asset_specification,
                            'item_id' => null,
                            'item_code' => null,
                            'item_name' => $item->asset_description . "-" . $item->asset_specification,
                            'remarks' => $item->remarks ?? null,
                            'quantity' => $item->quantity,
                            'r_warehouse_id' => $item->receivingWarehouse->id,
                            'r_warehouse_name' => $item->receivingWarehouse->warehouse_name,
                            'date_needed' => $item->date_needed,
                            'uom_id' => $item->uom->sync_id,
                            'uom_code' => $item->uom->uom_code,
                            'uom_name' => $item->uom->uom_name,
//                            $this->sendTransactionWithAttachments($referenceNumber),
                        ];
                    })->toArray();
                    return [
                        'v_name' => $assetRequest->requestor->firstname . ' ' . $assetRequest->requestor->lastname,
                        'rdf_id' => $assetRequest->requestor->employee_id, //preg_replace('/\D/', '', $employee_id),
                        'vrid' => $assetRequest->requester_id,
                        'pr_description' => $assetRequest->acquisition_details,
                        'pr_number' => (string)$assetRequest->pr_number,
                        'transaction_number' => $assetRequest->transaction_number,
                        "type_id" => "4",
                        "type_name" => "Asset",
                        'r_warehouse_id' => $assetRequest->receivingWarehouse->id,
                        'r_warehouse_name' => $assetRequest->receivingWarehouse->warehouse_name,
                        'company_id' => $assetRequest->company->sync_id,
                        'company_name' => $assetRequest->company->company_name,
                        'business_unit_id' => $assetRequest->businessUnit->sync_id,
                        'business_unit_name' => $assetRequest->businessUnit->business_unit_name,
                        'department_id' => $assetRequest->department->sync_id,
                        'department_name' => $assetRequest->department->department_name,
                        'department_unit_id' => $assetRequest->unit->sync_id,
                        'department_unit_name' => $assetRequest->unit->unit_name,
                        'sub_unit_id' => $assetRequest->subunit->sync_id,
                        'sub_unit_name' => $assetRequest->subunit->sub_unit_name,
                        'location_id' => $assetRequest->location->sync_id,
                        'location_name' => $assetRequest->location->location_name,
                        'account_title_id' => $assetRequest->accountingEntries->initialDebit->sync_id,
                        'account_title_name' => $assetRequest->accountingEntries->initialDebit->account_title_name,
                        'initial_debit_id' => $assetRequest->accountingEntries->initialDebit->sync_id,
                        'initial_debit_name' => $assetRequest->accountingEntries->initialDebit->account_title_name,
                        'description' => $assetRequest->acquisition_details,
                        'created_at' => $assetRequest->created_at,
                        'date_needed' => $assetRequest->date_needed,
                        'module_name' => 'Asset',
                        'sgp' => null,
                        'f1' => null,
                        'f2' => null,
                        'order' => $listOfItems
                    ];
                })->values();


            if (AssetRequest::where('transaction_number', $transactionNumber)->first()->is_pr_returned == 1) {
                $filteredAndGroupedAssetRequests = $filteredAndGroupedAssetRequests->first();
            }


//            $referenceNumber = [];
//            foreach ($filteredAndGroupedAssetRequests[0]['order'] as $order) {
//                $referenceNumber [] = $order['reference_no'];
//            }
//            return $referenceNumber;


            AssetRequest::where('transaction_number', $transactionNumber)->update(['is_pr_returned' => 0]);

//            $referenceNumber = $filteredAndGroupedAssetRequests->pluck('order.*.reference_no')->flatten()->toArray();

//            foreach ($referenceNumber as $reference) {
//                $this->sendTransactionWithAttachments($reference, count($referenceNumber));
//            }

            DB::commit();
            return $filteredAndGroupedAssetRequests;
        } catch (Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return $this->responseUnprocessable('An error occurred while processing the request.');
        }
    }


    public function sendTransactionWithAttachments($referenceNumber, $itemCount)
    {
        $apiUrl = config('ymir-api.ymir_put_api_url');
        $bearerToken = config('ymir-api.ymir_put_api_token');

        $assetRequest = AssetRequest::with('media')
            ->where('referenceNumber', $referenceNumber)
            ->first();

        if (!$assetRequest) {
            return response()->json(['error' => 'Asset request not found'], 404);
        }

        $attachments = collect([
            'letter_of_request',
            'quotation',
            'specification_form',
            'tool_of_trade',
            'other_attachments'
        ])->flatMap(function ($collection) use ($assetRequest) {
            return $assetRequest->getMedia($collection);
        })
            ->filter()
            ->map(function ($media) use ($itemCount) {
                try {
                    $filePath = $media->getPath();

                    // Validate file existence and readability
                    if (!file_exists($filePath) || !is_readable($filePath)) {
                        throw new \Exception("File not accessible: {$filePath}");
                    }

                    // Open file in binary-safe mode
                    $resource = fopen($filePath, 'rb');

                    if (!is_resource($resource)) {
                        throw new \Exception("Failed to open file: {$filePath}");
                    }

                    return [
                        'type' => 'pr',
                        'items' => $itemCount,
                        'file' => 'file[]', // API expects multiple files
                        'contents' => $resource,
                        'filename' => $this->sanitizeFilename($media->file_name),
                        'headers' => [
                            'Content-Type' => $media->mime_type,
                            'Content-Length' => $media->size
                        ]
                    ];
                } catch (\Exception $e) {
                    report($e);
                    return null;
                }
            })
            ->filter()
            ->toArray();


        if (empty($attachments)) {
            return $this->responseUnprocessable('No attachments found');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'Accept' => 'application/json',
            ])
                /*->withOptions([
                    'verify' => config('app.env') === 'production', // SSL verification
                    'timeout' => 30,
                ])*/
                ->attach($attachments)
                ->post($apiUrl);

            // Cleanup file resources
            array_walk($attachments, function ($attachment) {
                if (is_resource($attachment['contents'])) {
                    fclose($attachment['contents']);
                }
            });

            return $this->responseSuccess('File transfer successful', $response->json());

        } catch (\Exception $e) {
            return $this->responseUnprocessable('An error occurred while transferring files');
        }
    }

    protected function sanitizeFilename($filename)
    {
        // Remove special characters and spaces
        return preg_replace('/[^\w.-]/', '_', $filename);
    }


//TODO: NOT DONE
    public function sendToYmir(Request $request)
    {
        $assets = $this->requestToPR($request);
//        return $assets;
//        $assets = $request->all();
        $user_id = auth('sanctum')->user()->id;

        $date_today = Carbon::now()
            ->timeZone("Asia/Manila")
            ->format("Y-m-d H:i");

        $current_year = date("Y");
        $latest_pr = YmirPRTransaction::where("pr_year_number_id", "like", $current_year . "-V-%")
            ->orderByRaw("CAST(SUBSTRING_INDEX(pr_year_number_id, '-', -1) AS UNSIGNED) DESC")
            ->first();

        if ($latest_pr) {
            $latest_number = explode("-", $latest_pr->pr_year_number_id)[2];
            $new_number = (int)$latest_number + 1;
        } else {
            $new_number = 1;
        }
//    return $latest_number;

        $latest_pr_number = YmirPRTransaction::max("pr_number") ?? 0;
        $pr_number = $latest_pr_number + 1;

        foreach ($assets as $sync) {
            $pr_year_number_id =
                $current_year .
                "-FA-" .
                str_pad($new_number, 3, "0", STR_PAD_LEFT);


            $purchase_request = new YmirPRTransaction([
                "pr_year_number_id" => $pr_year_number_id,
                "pr_number" => $pr_number,
                "transaction_no" => $sync["transaction_number"],
                "pr_description" => $sync["pr_description"],
                "date_needed" => $sync["date_needed"],
                "user_id" => $user_id,
                "type_id" => "4",
                "type_name" => "Asset",
                "business_unit_id" => $sync["business_unit_id"],
                "business_unit_name" => $sync["business_unit_name"],
                "company_id" => $sync["company_id"],
                "company_name" => $sync["company_name"],
                "department_id" => $sync["department_id"],
                "department_name" => $sync["department_name"],
                "department_unit_id" => $sync["department_unit_id"],
                "department_unit_name" => $sync["department_unit_name"],
                "location_id" => $sync["location_id"],
                "location_name" => $sync["location_name"],
                "sub_unit_id" => $sync["sub_unit_id"],
                "sub_unit_name" => $sync["sub_unit_name"],
                "account_title_id" => $sync["account_title_id"],
                "account_title_name" => $sync["account_title_name"],
                "module_name" => "Asset",
                "transaction_number" => $sync["transaction_number"],
                "status" => "Approved",
                "asset" => $sync["asset"] ?? null,
                "sgp" => $sync["sgp"],
                "f1" => $sync["f1"],
                "f2" => $sync["f2"],
                "layer" => "1",
                "for_po_only" => $date_today,
                "vrid" => $sync["vrid"],
            ]);
            $purchase_request->save();

            $orders = $sync["order"];

            foreach ($orders as $index => $values) {
                YmirPRItem::create([
                    "transaction_id" => $purchase_request->id,
                    "item_code" => $values["item_code"],
                    "item_name" => $values["item_name"],
                    "uom_id" => "6",
                    "quantity" => $values["quantity"],
                    "remarks" => $values["remarks"],
                ]);
            }

            $new_number++;
            $pr_number++;
        }
        return $this->responseSuccess('PR No. sent to Ymir successfully');

    }

    public function returnFromYmir(Request $request)
    {
        $transactionNumber = $request->input('transaction_number');
        $prNumber = $request->input('pr_number');
        $reason = $request->input('reason');
        $causer = $request->input('causer');
        $itemIds = $request->input('item_ids');
        $action = $request->input('action');

        if (!$this->validateAssetRequestAndApproval($transactionNumber, $prNumber)) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        $this->updateAssetRequestAndApproval($transactionNumber, $prNumber, $itemIds, $action, $reason);
        $this->logActivityForTransaction($transactionNumber, $prNumber, $causer, $action, $reason);

        return $this->responseSuccess('Asset Request returned successfully');
    }

    protected function validateAssetRequestAndApproval($transactionNumber, $prNumber): bool
    {
        $assetRequestsExists = AssetRequest::where('transaction_number', $transactionNumber)->where('pr_number', $prNumber)->exists();
        $assetApprovalExists = AssetApproval::where('transaction_number', $transactionNumber)->exists();

        return $assetRequestsExists && $assetApprovalExists;
    }

    protected function ymirUpdate($prNumber)
    {
        $user_id = auth('sanctum')->user();
        $firstname = $user_id->firstname;
        $lastname = $user_id->lastname;
        $employee_id = $user_id->employee_id;
        $fullName = $firstname . ' ' . $lastname;

        $ymirApiUrl = config('ymir-api.ymir_put_pr_api_url');
        $ymirApiToken = config('ymir-api.ymir_put_pr_api_token');

        $client = new Client();
        $response = $client->request('PUT', $ymirApiUrl / $prNumber, [
            'headers' => ['Token ' => 'Bearer ' . $ymirApiToken],
            'json' => [
                'v_name' => $fullName,
                'rdf_id' => $employee_id

            ]
        ]);

        if ($response->getStatusCode() == 200) {
            return true;
        } else {
            return false;
        }
    }

    protected function updateAssetRequestAndApproval($transactionNumber, $prNumber, $itemIds, $action, $reason)
    {
        if (strtolower($action) === 'cancel') {
            $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
                ->where('pr_number', $prNumber)
                ->where('is_fa_approved', 1)
                ->get();

            $assetRequests->each(function ($assetRequest) use ($reason) {
                $assetRequest->update([
                    'pr_number' => null,
                    'filter' => 'Cancelled',
                    'status' => 'Cancelled',
                    'is_fa_approved' => false,
                    'is_pr_returned' => true,
                    'remarks' => $reason ?? null
                ]);
                $assetRequest->delete();
            });

            AssetApproval::where('transaction_number', $transactionNumber)
                ->update(['status' => 'Cancelled']);

        } else {
            AssetRequest::where('transaction_number', $transactionNumber)
                ->where('pr_number', $prNumber)
                ->update([
//                'pr_number' => null,
                    'filter' => 'Returned From Ymir',
                    'status' => 'Returned From Ymir',
                    'is_fa_approved' => false,
                    'is_pr_returned' => true,
                    'remarks' => $reason ?? null
                ]);
            AssetApproval::where('transaction_number', $transactionNumber)
                ->update(['status' => 'Returned From Ymir']);
        }

        foreach ($itemIds as $itemId) {
            AssetRequest::where('reference_number', $itemId['reference_no'])
                ->update([
                    'ymir_id' => $itemId['id'],
                ]);
        }

    }

    protected function logActivityForTransaction($transactionNumber, $prNumber, $causer, $action, $reason = null)
    {
        $assetRequest = new AssetRequest(); // Consider if a new instance is needed or if an existing instance should be used.
        activity()
            ->performedOn($assetRequest)
            ->withProperties(['transaction_number' => $transactionNumber, 'pr_number' => $prNumber, 'remarks' => $reason, 'causer' => $causer])
            ->inLog(strtolower($action) === 'cancel' ? 'Cancelled' : 'Returned')
            ->tap(function ($activity) use ($transactionNumber) {
                $activity->subject_id = $transactionNumber;
            })
            ->log(strtolower($action) === 'cancel' ? 'Cancelled' : 'Returned');
    }

    public function prReport(Request $request)
    {
        $perPage = $request->get('per_page');
        $from = $request->input('from');
        $to = $request->input('to');

        $from = $from ? Carbon::parse($from)->startOfDay() : null;
        $to = $to ? Carbon::parse($to)->endOfDay() : null;
        $export = $request->input('export');

        $assetRequests = AssetRequest::where('status', 'Approved')
            ->whereNotNull('pr_number')
            ->when($from && $to, function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [$from, $to]);
            })
            ->useFilters()
            ->orderBy('created_at', 'desc');

        if ($export) {
            $assetRequests = $assetRequests->get()->transform(function ($assetRequest) {
                try {
                    $YmirPRNumber = YmirPRTransaction::where('pr_number', $assetRequest->pr_number)->first()->pr_year_number_id ?? null;
                } catch (\Exception $e) {
                    $YmirPRNumber = $assetRequest->first()->pr_number;
                }
                return [
                    'ymir_pr_number' => $YmirPRNumber ?: '-',
                    'pr_number' => $assetRequest->pr_number,
                    'item_status' => $assetRequest->item_status,
                    'status' => $assetRequest->status == 'Approved' ? ($assetRequest->is_fa_approved ? $assetRequest->filter : 'For Approval of FA') : $assetRequest->status,
                    'asset_description' => $assetRequest->asset_description,
                    'asset_specification' => $assetRequest->asset_specification,
                    'brand' => $assetRequest->brand ?? '-',
                    'quantity' => $assetRequest->quantity,
                    'transaction_number' => $assetRequest->transaction_number,
                    'acquisition_details' => $assetRequest->acquisition_details,
                    'company_code' => $assetRequest->company->company_code,
                    'company' => $assetRequest->company->company_name,
                    'business_unit_code' => $assetRequest->businessUnit->business_unit_code,
                    'business_unit' => $assetRequest->businessUnit->business_unit_name,
                    'department_code' => $assetRequest->department->department_code,
                    'department' => $assetRequest->department->department_name,
                    'unit_code' => $assetRequest->unit->unit_code,
                    'unit' => $assetRequest->unit->unit_name,
                    'subunit_code' => $assetRequest->subunit->sub_unit_code,
                    'subunit' => $assetRequest->subunit->sub_unit_name,
                    'location_code' => $assetRequest->location->location_code,
                    'location' => $assetRequest->location->location_name,
//                    'account_title_code' => $assetRequest->accountTitle->account_title_code,
//                    'account_title' => $assetRequest->accountTitle->account_title_name,
                    'initial_debit_code' => $assetRequest->accountingEntries->initialDebit->account_title_code,
                    'initial_debit' => $assetRequest->accountingEntries->initialDebit->account_title_name,
                    'initial_credit_code' => $assetRequest->accountingEntries->initialCredit->account_title_code,
                    'initial_credit' => $assetRequest->accountingEntries->initialCredit->account_title_name,
                    'depreciation_debit_code' => $assetRequest->accountingEntries->depreciationDebit->account_title_code,
                    'depreciation_debit' => $assetRequest->accountingEntries->depreciationDebit->account_title_name,
                    'depreciation_credit_code' => $assetRequest->accountingEntries->depreciationCredit->credit_code,
                    'depreciation_credit' => $assetRequest->accountingEntries->depreciationCredit->credit_name,
                    'date_needed' => $assetRequest->date_needed,
                    'created_at' => $assetRequest->created_at,
                ];
            });
        } else {
            $assetRequests = $assetRequests->get()->groupBy('pr_number')->map(function ($assetRequestCollection) {
                try {
                    $YmirPRNumber = YmirPRTransaction::where('pr_number', $assetRequestCollection->first()->pr_number)->first()->pr_year_number_id ?? null;
                } catch (\Exception $e) {
                    $YmirPRNumber = $assetRequestCollection->first()->pr_number;
                }

                return [
                    'status' => $assetRequestCollection->first()->status == 'Approved' ? ($assetRequestCollection->first()->is_fa_approved ? $assetRequestCollection->first()->filter : 'For Approval of FA') : $assetRequestCollection->status,
                    'ymir_pr_number' => $YmirPRNumber ?: '-',
                    'pr_number' => $assetRequestCollection->first()->pr_number,
                    'pr_description' => $assetRequestCollection->first()->acquisition_details,
                    'date_needed' => $assetRequestCollection->first()->date_needed,
                    'company' => $assetRequestCollection->first()->company->company_name,
                    'company_code' => $assetRequestCollection->first()->company->company_code,
                    'business_unit' => $assetRequestCollection->first()->businessUnit->business_unit_name,
                    'business_unit_code' => $assetRequestCollection->first()->businessUnit->business_unit_code,
                    'department' => $assetRequestCollection->first()->department->department_name,
                    'department_code' => $assetRequestCollection->first()->department->department_code,
                    'unit' => $assetRequestCollection->first()->unit->unit_name,
                    'unit_code' => $assetRequestCollection->first()->unit->unit_code,
                    'subunit' => $assetRequestCollection->first()->subunit->sub_unit_name,
                    'subunit_code' => $assetRequestCollection->first()->subunit->sub_unit_code,
                    'location' => $assetRequestCollection->first()->location->location_name,
                    'location_code' => $assetRequestCollection->first()->location->location_code,
//                    'account_title' => $assetRequestCollection->first()->accountTitle->account_title_name,
//                    'account_title_code' => $assetRequestCollection->first()->accountTitle->account_title_code,
                    'initial_debit_code' => $assetRequestCollection->first()->accountingEntries->initialDebit->account_title_code ?? '-',
                    'initial_debit' => $assetRequestCollection->first()->accountingEntries->initialDebit->account_title_name ?? '-',
                    'initial_credit_code' => $assetRequestCollection->first()->accountingEntries->initialCredit->account_title_code ?? '-',
                    'initial_credit' => $assetRequestCollection->first()->accountingEntries->initialCredit->account_title_name ?? '-',
                    'depreciation_debit_code' => $assetRequestCollection->first()->accountingEntries->depreciationDebit->account_title_code ?? '-',
                    'depreciation_debit' => $assetRequestCollection->first()->accountingEntries->depreciationDebit->account_title_name ?? '-',
                    'depreciation_credit_code' => $assetRequestCollection->first()->accountingEntries->depreciationCredit->credit_code ?? '-',
                    'depreciation_credit' => $assetRequestCollection->first()->accountingEntries->depreciationCredit->credit_name ?? '-',
                    'items' => $assetRequestCollection->map(function ($item) {
                        return [
                            'asset_description' => $item->asset_description,
                            'asset_specification' => $item->asset_specification,
                            'brand' => $item->brand,
                            'quantity' => $item->quantity,
                            'date_needed' => $item->date_needed,
                        ];
                    }),
                ];
            })->values();

            if ($perPage !== null) {
                $page = $request->input('page', 1);
                $offset = $page * $perPage - $perPage;
                $assetRequests = new LengthAwarePaginator($assetRequests->slice($offset, $perPage)->values(), $assetRequests->count(), $perPage, $page, [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]);
            }
        }

        return $assetRequests;
    }

}
