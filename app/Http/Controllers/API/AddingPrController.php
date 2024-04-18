<?php

namespace App\Http\Controllers\API;


use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Traits\AddingPRHandler;
use App\Traits\RequestShowDataHandler;
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
            $page = $request->input('page', 1);
            $offset = $page * $perPage - $perPage;
            $assetRequest = new LengthAwarePaginator($assetRequest->slice($offset, $perPage)->values(), $assetRequest->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
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
        $filter = $request->input('filter', 'old');
        $perPage = $request->input('per_page', null);
        $pagination = $request->input('pagination', null);

        $assetRequest = AssetRequest::where('status', 'Approved')
            ->whereNull('pr_number')
            ->whereNull('deleted_at')
            ->when($filter !== 'old', function($query) use($filter){
                //filter all that item that is created only today
                return $query->where('created_at', '>=', now()->startOfDay());
            })
//            ->when($toPr !== null, function ($query) use ($toPr) {
//                return $query->where($toPr == 0 ? 'pr_number' : 'pr_number', $toPr == 0 ? '!=' : '=', null);
//            })
            ->useFilters()
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('transaction_number')
            ->map(function ($assetRequestCollection) {
                $assetRequest = $assetRequestCollection->first();
                $listOfItems = $assetRequestCollection->map(function ($item) {
                    return [
//                        'item_id' => $item->id,
                        'item_code' => $item->reference_number,
                        'item_name' => $item->asset_description,
                        'remarks' => $item->asset_specification,
                        'quantity' => $item->quantity,
//                        'created_at' => $item->created_at,
                        //                        'additional_info' => $item->additional_info,
//                        'accountability' => $item->accountability,
//                        'accountable' => $item->accountable == '-' ? null : $item->accountable,
//                        'cell_number' => $item->cell_number,
//                        'brand' => $item->brand,
//                        'remarks' => $item->remarks,

//                        'date_needed' => $item->date_needed,
                        // Add more fields as needed
                    ];
                })->toArray();
                return [
                    'transaction_number' => $assetRequest->transaction_number,
                    'company_id' => $assetRequest->company->sync_id,
                    'company_code' => $assetRequest->company->company_code,
                    'company_name' => $assetRequest->company->company_name,

                    'business_unit_id' => $assetRequest->businessUnit->sync_id,
                    'business_unit_code' => $assetRequest->businessUnit->business_unit_code,
                    'business_unit_name' => $assetRequest->businessUnit->business_unit_name,

                    'department_id' => $assetRequest->department->sync_id,
                    'department_code' => $assetRequest->department->department_code,
                    'department_name' => $assetRequest->department->department_name,

                    'unit_id' => $assetRequest->unit->sync_id,
                    'unit_code' => $assetRequest->unit->unit_code,
                    'unit_name' => $assetRequest->unit->unit_name,

                    'subunit_id' => $assetRequest->subunit->sync_id,
                    'subunit_code' => $assetRequest->subunit->sub_unit_code,
                    'subunit_name' => $assetRequest->subunit->sub_unit_name,

                    'location_id' => $assetRequest->location->sync_id,
                    'location_code' => $assetRequest->location->location_code,
                    'location_name' => $assetRequest->location->location_name,

                    'account_title_id' => $assetRequest->accountTitle->sync_id,
                    'account_title_code' => $assetRequest->accountTitle->account_title_code,
                    'account_title_name' => $assetRequest->accountTitle->account_title_name,
                    'description' => $assetRequest->acquisition_details,
                    'created_at' => $assetRequest->created_at,

                    'order' => $listOfItems
                ];
            })
            ->values();

        if ($perPage !== null && $pagination == null) {
            $page = $request->input('page', 1);
            $offset = $page * $perPage - $perPage;
            $assetRequest = new LengthAwarePaginator($assetRequest->slice($offset, $perPage)->values(), $assetRequest->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        return $assetRequest;
    }


    public function transformForYmir($assetRequest): array
    {
        $deletedQuantity = AssetRequest::onlyTrashed()->where('transaction_number', $assetRequest->transaction_number)->sum('quantity');
        return [
            'company' => [
                'id' => $assetRequest->company->sync_id,
                'company_code' => $assetRequest->company->company_code,
                'company_name' => $assetRequest->company->company_name,
            ],
            'business_unit' => [
                'id' => $assetRequest->businessUnit->sync_id,
                'business_unit_code' => $assetRequest->businessUnit->business_unit_code,
                'business_unit_name' => $assetRequest->businessUnit->business_unit_name,
            ],
            'department' => [
                'id' => $assetRequest->department->sync_id,
                'department_code' => $assetRequest->department->department_code,
                'department_name' => $assetRequest->department->department_name,
            ],
            'subunit' => [
                'id' => $assetRequest->subunit->sync_id,
                'subunit_code' => $assetRequest->subunit->subunit_code,
                'subunit_name' => $assetRequest->subunit->subunit_name,
            ],
            'location' => [
                'id' => $assetRequest->location->sync_id,
                'location_code' => $assetRequest->location->location_code,
                'location_name' => $assetRequest->location->location_name,
            ],
            'list_of_items' => [

            ]
        ];
    }
}
