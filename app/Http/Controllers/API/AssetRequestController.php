<?php

namespace App\Http\Controllers\API;

use App\Events\AssetRequestUpdatedCheck;
use App\Models\User;
use App\Models\Company;
use App\Models\SubUnit;
use App\Models\SubCapex;
use App\Models\Approvers;
use App\Models\Department;
use App\Models\AssetRequest;
use App\Traits\RequestShowDataHandler;
use Illuminate\Http\Request;
use App\Models\ApproverLayer;
use App\Models\AssetApproval;
use App\Models\RoleManagement;
use App\Models\RequestContainer;
use Illuminate\Http\JsonResponse;
use App\Traits\AssetRequestHandler;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\DepartmentUnitApprovers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use App\Repositories\ApprovedRequestRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\AssetRequest\CreateAssetRequestRequest;
use App\Http\Requests\AssetRequest\UpdateAssetRequestRequest;

class AssetRequestController extends Controller
{
    use ApiResponse, AssetRequestHandler, RequestShowDataHandler;

    private $approveRequestRepository;
    protected $isDataUpdated = 'false';

    public function __construct(ApprovedRequestRepository $approveRequestRepository)
    {
        $this->approveRequestRepository = $approveRequestRepository;
    }

    public function index(Request $request)
    {
        $request->validate([
            'for_monitoring' => 'boolean',
            'filter' => ['nullable', 'string'],
        ]);

        $forMonitoring = $request->for_monitoring ?? false;

        $requesterId = auth('sanctum')->user()->id;
        $role = Cache::remember("user_role_$requesterId", 60, function () use ($requesterId) {
            return User::find($requesterId)->roleManagement->role_name;
        });
        // $role = User::find($requesterId)->roleManagement->role_name;
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];

        $perPage = $request->input('per_page', null);
        $filter = $request->input('filter', null);
        $filter = $filter ? explode(',', $filter) : [];
        $filter = array_map('trim', $filter);

        $conditions = [
            'Returned' => ['status' => 'Returned'],
            'For Approval' => ['status' => ['like', 'For Approval%']],
            'For PR' => ['status' => 'Approved', 'pr_number' => null],
            'For PO' => ['status' => 'Approved', 'pr_number' => ['!=', null], 'quantity' => ['!=', DB::raw('quantity_delivered')]],
            'For Tagging' => ['status' => 'Approved', 'pr_number' => ['!=', null], 'po_number' => ['!=', null], 'is_claimed' => 0, 'print_count' => ['!=', DB::raw('quantity')], 'is_addcost' => 0,  'quantity' => ['=', DB::raw('quantity_delivered')] ] ,
            'For Pickup' => ['status' => 'Approved', 'pr_number' => ['!=', null], 'po_number' => ['!=', null], 'is_claimed' => 0, 'quantity' => ['=', DB::raw('quantity_delivered')], 'print_count' => ['=', DB::raw('quantity')]],
            'Released' => ['is_claimed' => 1],
        ];

        $assetRequest = AssetRequest::query();

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        if (!$forMonitoring) {
            $assetRequest->where('requester_id', $requesterId);
        }

        if (!empty($filter)) {
            $assetRequest->where(function ($query) use ($filter, $conditions) {
                foreach ($filter as $key) {
                    if (isset($conditions[$key])) {
                        $query->orWhere(function ($query) use ($conditions, $key) {
                            foreach ($conditions[$key] as $field => $value) {
                                if (is_array($value)) {
                                    $query->where($field, $value[0], $value[1]);
                                } else {
                                    $query->where($field, $value);
                                }
                            }
                        });
                    }
                }
            });
        }

        $assetRequest = $assetRequest->orderByDesc('created_at')->useFilters();
        $assetRequest = $assetRequest
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


    public function store(CreateAssetRequestRequest $request): JsonResponse
    {
        $userRequest = $request->userRequest;
        $requesterId = auth('sanctum')->user()->id;
        $transactionNumber = AssetRequest::generateTransactionNumber();
        $departmentUnitApprovers = DepartmentUnitApprovers::with('approver')
            ->where('subunit_id', $userRequest[0]['subunit_id'])
            ->orderBy('layer', 'asc')
            ->get();

        $layerIds = $departmentUnitApprovers
            ->map(function ($approverObject) {
                return $approverObject->approver->approver_id;
            })
            ->toArray();

        $isRequesterApprover = in_array($requesterId, $layerIds);
        $requesterLayer = array_search($requesterId, $layerIds) + 1;
        $maxLayer = $departmentUnitApprovers->max('layer');
        $isLastApprover = $maxLayer == $requesterLayer;

        foreach ($userRequest as $request) {
            $assetRequest = AssetRequest::create([
                'status' => $isLastApprover ? 'Approved' : ($isRequesterApprover ? 'For Approval of Approver ' . ($requesterLayer + 1) : 'For Approval'),
                'requester_id' => $requesterId,
                'transaction_number' => $transactionNumber,
                'reference_number' => (new AssetRequest())->generateReferenceNumber(),
                'type_of_request_id' => $request['type_of_request_id']['id'],
                'additional_info' => $request['additional_info'] ?? null,
                'acquisition_details' => $request['acquisition_details'],
                'attachment_type' => $request['attachment_type'],
                'subunit_id' => $request['subunit_id']['id'],
                'location_id' => $request['location_id']['id'],
                'account_title_id' => $request['account_title_id']['id'],
                'accountability' => $request['accountability'],
                'company_id' => $request['department_id']['company']['company_id'],
                'department_id' => $request['department_id']['id'],
                'accountable' => $request['accountable'] ?? null,
                'asset_description' => $request['asset_description'],
                'asset_specification' => $request['asset_specification'] ?? null,
                'cellphone_number' => $request['cellphone_number'] ?? null,
                'brand' => $request['brand'] ?? null,
                'quantity' => $request['quantity'],
            ]);

            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];

            foreach ($fileKeys as $fileKey) {
                if (isset($request[$fileKey])) {
                    $files = is_array($request[$fileKey]) ? $request[$fileKey] : [$request[$fileKey]];
                    foreach ($files as $file) {
                        $assetRequest->addMedia($file)->toMediaCollection($fileKey);
                    }
                }
            }
        }

        $this->createAssetApprovals($departmentUnitApprovers, $isRequesterApprover, $requesterLayer, $assetRequest, $requesterId);

        return $this->responseCreated('AssetRequest created successfully');
    }

    public function show($transactionNumber)
    {
        $requestorId = auth('sanctum')->user()->id;
        $approverCheck = Approvers::where('approver_id', $requestorId)->first();
        if ($approverCheck) {
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->dynamicPaginate();
        } else {
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)
                ->where('requester_id', $requestorId)->dynamicPaginate();
        }
        $assetRequest = $this->responseData($assetRequest);

        if ($assetRequest->isEmpty()) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        return $assetRequest;
    }

    public function update(UpdateAssetRequestRequest $request, $referenceNumber): JsonResponse
    {
        $assetRequest = $this->getAssetRequest('reference_number', $referenceNumber);
        if (!$assetRequest) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        $updateResult = $this->updateAssetRequest($assetRequest, $request);
        if ($updateResult) {
            $this->handleMediaAttachments($assetRequest, $request);
        }
        return $this->responseSuccess('AssetRequest updated Successfully');
    }

    public function resubmitRequest(CreateAssetRequestRequest $request)
    {
        $isFileDataUpdated = Cache::get('isFileDataUpdated');
        $isDataUpdated = Cache::get('isDataUpdated');
//        return "isFileDataUpdated: $isFileDataUpdated, isDataUpdated: $isDataUpdated";
        $transactionNumber = $request->transaction_number;
        if ($isDataUpdated == 'true' || $isFileDataUpdated == 'true') {
            $this->approveRequestRepository->resubmitRequest($transactionNumber);
            Cache::forget('isDataUpdated');
            Cache::forget('isFileDataUpdated');
            return $this->responseSuccess('AssetRequest resubmitted Successfully');
        } else {
            return $this->responseUnprocessable('No changes, need to update first.');
//            return $this->responseUnprocessable('You can\'t resubmit this request.');
        }
    }

    public function updateRequest(UpdateAssetRequestRequest $request, $referenceNumber)
    {
        $assetRequest = $this->getAssetRequest('reference_number', $referenceNumber);
        if (!$assetRequest) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        // Make changes to the $assetRequest and $ar objects but don't save them
        $assetRequest = $this->updateAssetRequest($assetRequest, $request, $save = false);
        // Check if the $assetRequest and $ar objects are dirty
        $isDataUpdated = $assetRequest->isDirty() ? 'true' : 'false';

        // Save the changes to the $assetRequest and $ar objects
        $assetRequest->save();

        $this->handleMediaAttachments($assetRequest, $request);

        Cache::put('isDataUpdated', $isDataUpdated, 60);
        return $this->responseSuccess('AssetRequest updated Successfully');
    }

    public function removeRequestItem($transactionNumber, $referenceNumber = null)
    {

        if ($transactionNumber && $referenceNumber) {
            //            return 'both';
            return $this->deleteRequestItem($referenceNumber, $transactionNumber);
        }
        if ($transactionNumber) {
            //            return 'single';
            return $this->deleteAssetRequest($transactionNumber);
        }
    }

    public function moveData()
    {
        // Get the requester id from the request
        $requesterId = auth('sanctum')->user()->id;
        $transactionNumber = AssetRequest::generateTransactionNumber();

        // Get the items from Request-container
        $items = RequestContainer::where('requester_id', $requesterId)->get();
        if($items->isEmpty()){
            return $this->responseUnprocessable('No data to move');
        }
        //check if the item inside item have different subunit id
        $subunitId = $items[0]->subunit_id;
        foreach ($items as $item) {
            if ($item->subunit_id != $subunitId) {
                return $this->responseUnprocessable('Invalid Action, Different Subunit');
            }
        }

        foreach ($items as $item) {
            $assetRequest = new AssetRequest();

            $assetRequest->status = $item->status;
            $assetRequest->is_addcost = $item->is_addcost;
            $assetRequest->fixed_asset_id = $item->fixed_asset_id;
            $assetRequest->requester_id = $item->requester_id;
            $assetRequest->type_of_request_id = $item->type_of_request_id;
            $assetRequest->attachment_type = $item->attachment_type;
            $assetRequest->subunit_id = $item->subunit_id;
            $assetRequest->location_id = $item->location_id;
            $assetRequest->account_title_id = $item->account_title_id;
            $assetRequest->additional_info = $item->additional_info;
            $assetRequest->acquisition_details = $item->acquisition_details;
            $assetRequest->accountability = $item->accountability;
            $assetRequest->company_id = $item->company_id;
            $assetRequest->department_id = $item->department_id;
            $assetRequest->accountable = $item->accountable;
            $assetRequest->asset_description = $item->asset_description;
            $assetRequest->asset_specification = $item->asset_specification;
            $assetRequest->cellphone_number = $item->cellphone_number;
            $assetRequest->brand = $item->brand;
            $assetRequest->quantity = $item->quantity;

            // Add transaction number and reference number
            $assetRequest->transaction_number = $transactionNumber;
            $assetRequest->reference_number = $assetRequest->generateReferenceNumber();

            $assetRequest->save();
            // $assetRequest->reference_number = str_pad($assetRequest->id, 4, '0', STR_PAD_LEFT);
            // $assetRequest->save();

            // Get the media from RequestContainer and put it in AssetRequest
            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];

            foreach ($fileKeys as $fileKey) {
                $media = $item->getMedia($fileKey);
                foreach ($media as $file) {
                    $file->copy($assetRequest, $fileKey);
                }
            }

            // Delete the item from RequestContainer
            $item->delete();
        }

        $this->createAssetApprovals($items, $requesterId, $assetRequest);

        return $this->responseSuccess('Successfully requested');
    }

    public function showById($id)
    {
        $assetRequest = AssetRequest::find($id);
        if (!$assetRequest) {
            return $this->responseUnprocessable('Asset Request not found.');
        }
        return $this->transformForSingleItemOnly($assetRequest);
    }

    public function getPerRequest($transactionNumber)
    {
        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)
            ->orderByDesc('created_at')
            ->useFilters()
            ->get()
            ->groupBy('transaction_number')
            ->map(function ($assetRequestCollection) {
                $assetRequest = $assetRequestCollection->first();
                //sum all the quantity per group
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                return $this->transformIndexAssetRequest($assetRequest);
            })
            ->values();

        return $assetRequest;
    }
}
