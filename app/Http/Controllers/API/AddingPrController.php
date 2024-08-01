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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\AssetRequestHandler;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
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

    //This is for Ymir
    public function requestToPR(Request $request)
    {

//        $toPr = $request->get('toPr', null);
//        $filter = $request->input('filter', 'old');
        $transactionNumber = $request->input('transaction_number', null);
        $perPage = $request->input('per_page', null);
        $pagination = $request->input('pagination', null);
        $prNumber = AssetRequest::generatePRNumber();


//        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
//        $endDate = Carbon::parse($request->input('end_date'))->endOfDay();


        $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
            ->useFilters()
            ->orderBy('created_at', 'desc')
            ->get()
            ->each(function ($assetRequest) use ($prNumber) {
                // Check if the asset request already has a PR number
                if (is_null($assetRequest->pr_number)) {
                    $assetRequest->update([
                        'pr_number' => $prNumber,
                    ]);
                }
            });

        $filteredAndGroupedAssetRequests = $assetRequests->fresh()
            ->where('status', 'Approved')
            ->where('is_fa_approved', false)
//            ->whereNotNull('pr_number')
            ->whereNull('deleted_at')
//            ->useFilters()
//            ->orderBy('created_at', 'desc')
//            ->get()
            ->groupBy('transaction_number')
            ->map(function ($assetRequestCollection) {
                $latestDateNeeded = $assetRequestCollection->max('date_needed');
                $assetRequest = $assetRequestCollection->first();
                $assetRequest->date_needed = $latestDateNeeded;
                $listOfItems = $assetRequestCollection->map(function ($item) {
                    return [
//                        'item_id' => $item->id,
                        'reference_number' => $item->pr_number,
                        'item_code' => $item->reference_number,
                        'item_name' => $item->asset_description,
                        'remarks' => $item->asset_specification, //TODO:check
                        'quantity' => $item->quantity,
//                        'created_at' => $item->created_at,
                        //                        'additional_info' => $item->additional_info,
//                        'accountability' => $item->accountability,
//                        'accountable' => $item->accountable == '-' ? null : $item->accountable,
//                        'cell_number' => $item->cell_number,
//                        'brand' => $item->brand,
//                        'remarks' => $item->remarks,

                        'date_needed' => $item->date_needed,
                        'uom_id' => $item->uom->sync_id,
                        'uom_code' => $item->uom->uom_code,
                        'uom_name' => $item->uom->uom_name,
                        // Add more fields as needed
                    ];
                })->toArray();
                return [
                    'vrid' => $assetRequest->requester_id, //vladimir requester ID\
                    'pr_description'=> $assetRequest->acquisition_details,
                    'pr_number' => $assetRequest->pr_number,
                    'transaction_number' => $assetRequest->transaction_number,

                    'r_warehouse_id' => $assetRequest->receivingWarehouse->id,
                    'r_warehouse_name' => $assetRequest->receivingWarehouse->warehouse_name,

                    'company_id' => $assetRequest->company->sync_id,
//                    'company_code' => $assetRequest->company->company_code,
                    'company_name' => $assetRequest->company->company_name,

                    'business_unit_id' => $assetRequest->businessUnit->sync_id,
//                    'business_unit_code' => $assetRequest->businessUnit->business_unit_code,
                    'business_unit_name' => $assetRequest->businessUnit->business_unit_name,

                    'department_id' => $assetRequest->department->sync_id,
//                    'department_code' => $assetRequest->department->department_code,
                    'department_name' => $assetRequest->department->department_name,

                    'department_unit_id' => $assetRequest->unit->sync_id,
//                    'department_unit_code' => $assetRequest->unit->unit_code,
                    'department_unit_name' => $assetRequest->unit->unit_name,

                    'sub_unit_id' => $assetRequest->subunit->sync_id,
//                    'subunit_code' => $assetRequest->subunit->sub_unit_code,
                    'sub_unit_name' => $assetRequest->subunit->sub_unit_name,

                    'location_id' => $assetRequest->location->sync_id,
//                    'location_code' => $assetRequest->location->location_code,
                    'location_name' => $assetRequest->location->location_name,

                    'account_title_id' => $assetRequest->accountTitle->sync_id,
//                    'account_title_code' => $assetRequest->accountTitle->account_title_code,
                    'account_title_name' => $assetRequest->accountTitle->account_title_name,
                    'description' => $assetRequest->acquisition_details,
                    'created_at' => $assetRequest->created_at,
                    'date_needed' => $assetRequest->date_needed,
//                    'module_name' => 'Purchase Request',
                    'sgp' => null,
                    'f1' => null,
                    'f2' => null,
                    'order' => $listOfItems
                ];
            })
            ->values();


        if ($perPage !== null && $pagination == null) {
            $page = $request->input('page', 1);
            $offset = $page * $perPage - $perPage;
            $filteredAndGroupedAssetRequests = new LengthAwarePaginator($filteredAndGroupedAssetRequests->slice($offset, $perPage)->values(), $filteredAndGroupedAssetRequests->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        return $filteredAndGroupedAssetRequests;
    }


//TODO: NOT DONE
    public function sendToYmir(Request $request)
    {
        $assets = $this->requestToPR($request);
//        return $assets;
//        $assets = $request->all();
        $user_id = Auth()->user()->id;

        $date_today = Carbon::now()
            ->timeZone("Asia/Manila")
            ->format("Y-m-d H:i");

        $current_year = date("Y");
        $latest_pr = YmirPRTransaction::where("pr_year_number_id", "like", $current_year . "-V-%")
            ->orderByRaw("CAST(SUBSTRING_INDEX(pr_year_number_id, '-', -1) AS UNSIGNED) DESC")
            ->first();

        if ($latest_pr) {
            $latest_number = explode("-", $latest_pr->pr_year_number_id)[2];
            $new_number = (int)$latest_number+1;
        } else {
            $new_number = 1;
        }
//    return $latest_number;

        $latest_pr_number = YmirPRTransaction::max("pr_number") ?? 0;
        $pr_number = $latest_pr_number + 1;

        foreach ($assets as $sync) {
            $pr_year_number_id =
                $current_year .
                "-V-" .
                str_pad($new_number, 3, "0", STR_PAD_LEFT);


            $purchase_request = new YmirPRTransaction([
                "pr_year_number_id" => $pr_year_number_id,
                "pr_number" => $pr_number,
                "transaction_no" => $sync["transaction_number"],
                "pr_description" => $sync["pr_description"],
                "date_needed" => $sync["date_needed"],
                "user_id" => $user_id,
                "type_id" => "4",
                "type_name" => "Assets",
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
                "module_name" => "Assets",
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

    public function returnFormYmir(Request $request)
    {
        $transactionNumber = $request->input('transaction_number');
        $remarks = $request->input('remarks');

        if (!$this->validateAssetRequestAndApproval($transactionNumber)) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        $this->updateAssetRequestAndApproval($transactionNumber, $remarks);
        $this->logActivityForTransaction($transactionNumber);

        return $this->responseSuccess('Asset Request returned successfully');
    }

    protected function validateAssetRequestAndApproval($transactionNumber): bool
    {
        $assetRequestsExists = AssetRequest::where('transaction_number', $transactionNumber)->exists();
        $assetApprovalExists = AssetApproval::where('transaction_number', $transactionNumber)->exists();

        return $assetRequestsExists && $assetApprovalExists;
    }

    protected function updateAssetRequestAndApproval($transactionNumber, $remarks)
    {
        AssetRequest::where('transaction_number', $transactionNumber)
            ->update([
                'pr_number' => null,
                'filter' => 'Returned From Ymir',
                'status' => 'Returned From Ymir',
                'is_fa_approved' => false,
                'remarks' => $remarks ?? null
            ]);

        AssetApproval::where('transaction_number', $transactionNumber)
            ->update(['status' => 'Returned From Ymir']);
    }

    protected function logActivityForTransaction($transactionNumber)
    {
        $assetRequest = new AssetRequest(); // Consider if a new instance is needed or if an existing instance should be used.
        activity()
            ->performedOn($assetRequest)
            ->tap(function ($activity) use ($transactionNumber) {
                $activity->subject_id = $transactionNumber;
            })
            ->log('Returned from Ymir');
    }
}
