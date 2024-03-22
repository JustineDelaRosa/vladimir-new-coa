<?php

namespace App\Http\Controllers\API;

use App\Events\AssetRequestUpdatedCheck;
use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Models\User;
use App\Models\Company;
use App\Models\SubUnit;
use App\Models\SubCapex;
use App\Models\Approvers;
use App\Models\Department;
use App\Models\AssetRequest;
use App\Traits\AssetReleaseHandler;
use App\Traits\ItemDetailsHandler;
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
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use App\Repositories\ApprovedRequestRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\AssetRequest\CreateAssetRequestRequest;
use App\Http\Requests\AssetRequest\UpdateAssetRequestRequest;

class AssetRequestController extends Controller
{
    use ApiResponse, AssetRequestHandler, RequestShowDataHandler, ItemDetailsHandler;

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
        $status = $request->input('status', 'active');
        $filter = $request->input('filter', null);
        $filter = $filter ? explode(',', $filter) : [];
        $filter = array_map('trim', $filter);

        $conditions = [
            'Returned' => ['status' => 'Returned'],
            'For Approval' => ['status' => ['like', 'For Approval%']],
            'For PR' => ['status' => 'Approved', 'pr_number' => null],
            'For PO' => ['status' => 'Approved', 'filter' => 'To PO'],
            'For Tagging' => ['status' => 'Approved', 'filter' => 'Received'], //'filter' => 'Ready to Pickup'
            'For Pickup' => ['status' => 'Approved', 'filter' => 'Ready to Pickup'],
            'Released' => ['is_claimed' => 1],
        ];

        $assetRequest = AssetRequest::query();

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }
        if ($status == 'deactivated') {
            $assetRequest->withTrashed();
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
            ->map(function ($assetRequestCollection) use ($filter, $status) {
                // If 'Deleted' filter is active and all items in the group are trashed, include the group in the result
//                if (in_array('Deleted', $filter) && $assetRequestCollection->every->trashed()) {
//                    $assetRequest = $assetRequestCollection->first();
//                    $assetRequest->quantity = $assetRequestCollection->sum('quantity');
//                    return $this->transformIndexAssetRequest($assetRequest);
//                }
                // If status is 'deactivated', check if all items in the group are trashed
                if ($status == 'deactivated' && $assetRequestCollection->every->trashed()) {
                    $assetRequest = $assetRequestCollection->first();
                    $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                    return $this->transformIndexAssetRequest($assetRequest);
                } // If status is 'active', check if any item in the group is not trashed id all is trash return null
                else if ($status == 'active' && !$assetRequestCollection->every->trashed()) {
                    $assetRequest = $assetRequestCollection->first();
                    $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                    return $this->transformIndexAssetRequest($assetRequest);
                }
            })
            ->filter()
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
        $requestorId = auth('sanctum')->user();
        $roleName = $requestorId->role->role_name;
        $adminCheck = ($roleName == 'Admin' || $roleName == 'Super Admin');

        // Get the transaction numbers of the non-soft-deleted records
        $nonSoftDeletedTransactionNumbers = AssetRequest::whereNull('deleted_at')->pluck('reference_number');

        $assetRequestQuery = AssetRequest::withTrashed()->where('transaction_number', $transactionNumber);

        if (!$adminCheck) {
            $assetRequestQuery->where('requester_id', $requestorId->id);
        }

        // Exclude the soft-deleted records that have the same transaction number as the non-soft-deleted records
        $assetRequestQuery->where(function ($query) use ($nonSoftDeletedTransactionNumbers) {
            $query->whereNull('deleted_at')
                ->orWhere(function ($query) use ($nonSoftDeletedTransactionNumbers) {
                    $query->whereNotNull('deleted_at')
                        ->whereNotIn('reference_number', $nonSoftDeletedTransactionNumbers);
                });
        });

        $assetRequestQuery->orderByRaw('deleted_at IS NULL DESC');

        $assetRequest = $this->responseData($assetRequestQuery->dynamicPaginate());

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
        $transactionNumber = AssetRequest::where('reference_number', $referenceNumber)->first()->transaction_number;
        $assetRequest = $this->getAssetRequestForApprover('reference_number', $transactionNumber, $referenceNumber);
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

        //TODO: Make this last for only 20 mins if there is bug
        Cache::put('isDataUpdated', $isDataUpdated, 60);
        $this->approveRequestRepository->isApproverChange($transactionNumber);
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
        if ($items->isEmpty()) {
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
            $assetRequest->additional_info = $item->additional_info;
            $assetRequest->acquisition_details = $item->acquisition_details;
            $assetRequest->accountability = $item->accountability;
            $assetRequest->company_id = $item->company_id;
            $assetRequest->business_unit_id = $item->business_unit_id;
            $assetRequest->department_id = $item->department_id;
            $assetRequest->unit_id = $item->unit_id;
            $assetRequest->subunit_id = $item->subunit_id;
            $assetRequest->location_id = $item->location_id;
            $assetRequest->account_title_id = $item->account_title_id;
            $assetRequest->accountable = $item->accountable;
            $assetRequest->asset_description = $item->asset_description;
            $assetRequest->asset_specification = $item->asset_specification;
            $assetRequest->cellphone_number = $item->cellphone_number;
            $assetRequest->brand = $item->brand;
            $assetRequest->quantity = $item->quantity;
            $assetRequest->date_needed = $item->date_needed;

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
            $item->forceDelete();
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

    public function downloadAttachments(Request $request)
    {
        $assetRequest = AssetRequest::find($request->id);

        if (!$assetRequest) {
            return $this->responseUnprocessable('No asset request found');
        }

        $media = $assetRequest->getMedia($request->attachment);

        if ($media->isEmpty()) {
            return $this->responseUnprocessable('No attachment found');
        }

        return response()->download($media->first()->getPath());
    }

    public function getItemDetails($referenceNumber, Request $request)
    {
        $perPage = $request->input('per_page', null);
//        $search = $request->input('search', null);
        $user = auth('sanctum')->user();
        $allowedRole = ['Super Admin', 'Admin', 'ERP'];

        $fixedAsset = FixedAsset::join('users', 'fixed_assets.requester_id', '=', 'users.id')
            ->join('type_of_requests', 'fixed_assets.type_of_request_id', '=', 'type_of_requests.id')
            ->join('suppliers', 'fixed_assets.supplier_id', '=', 'suppliers.id')
            ->join('companies', 'fixed_assets.company_id', '=', 'companies.id')
            ->join('business_units', 'fixed_assets.business_unit_id', '=', 'business_units.id')
            ->join('sub_units', 'fixed_assets.subunit_id', '=', 'sub_units.id')
            ->join('departments', 'fixed_assets.department_id', '=', 'departments.id')
            ->join('locations', 'fixed_assets.location_id', '=', 'locations.id')
            ->join('account_titles', 'fixed_assets.account_id', '=', 'account_titles.id')
            ->select(
                'fixed_assets.id',
                'users.username as requester',
                'transaction_number',
                'reference_number',
                'pr_number',
                'po_number',
                'vladimir_tag_number',
                'asset_description',
                'asset_specification',
                'type_of_requests.type_of_request_name as type_of_request',
                'suppliers.supplier_name as supplier',
                'accountability',
                'accountable',
                'received_by',
                'cellphone_number',
                'brand',
                'receipt',
                'quantity',
                'acquisition_date',
                'acquisition_cost',
                DB::raw("NULL as remarks"),
                DB::raw("'Served' as status"),
                DB::raw('CONCAT(companies.company_code, " - ", companies.company_name) as company'),
                DB::raw('CONCAT(business_units.business_unit_code, " - ", business_units.business_unit_name) as business_unit'),
                DB::raw('CONCAT(sub_units.sub_unit_code, " - ", sub_units.sub_unit_name) as sub_unit'),
                DB::raw('CONCAT(departments.department_code, " - ", departments.department_name) as department'),
                DB::raw('CONCAT(locations.location_code, " - ", locations.location_name) as location'),
                DB::raw('CONCAT(account_titles.account_title_code, " - ", account_titles.account_title_name) as account_title'),
                DB::raw('NULL as add_cost_sequence'),
            )
            ->where('reference_number', $referenceNumber)
            ->get()
            ->map(function ($item) {
                $collectionName = Str::slug($item->received_by) . '-signature';
                $signature = $item->getFirstMedia($collectionName);
                $item->attachments = [
                    'signature' => $signature ? [
                        'id' => $signature->id,
                        'file_name' => $signature->file_name,
                        'file_path' => $signature->getPath(),
                        'file_url' => $signature->getUrl(),
                        'collection_name' => $signature->collection_name,
//                        'viewing' => $this->convertImageToBase64($signature->getPath()),
                    ] : null,
                ];
                unset($item->media); // Remove the 'media' property from the response
                return $item;
            });


        $additionalCost = AdditionalCost::join('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            ->join('users', 'additional_costs.requester_id', '=', 'users.id')
            ->join('type_of_requests', 'additional_costs.type_of_request_id', '=', 'type_of_requests.id')
            ->join('suppliers', 'additional_costs.supplier_id', '=', 'suppliers.id')
            ->join('companies', 'additional_costs.company_id', '=', 'companies.id')
            ->join('business_units', 'additional_costs.business_unit_id', '=', 'business_units.id')
            ->join('sub_units', 'additional_costs.subunit_id', '=', 'sub_units.id')
            ->join('departments', 'additional_costs.department_id', '=', 'departments.id')
            ->join('locations', 'additional_costs.location_id', '=', 'locations.id')
            ->join('account_titles', 'additional_costs.account_id', '=', 'account_titles.id')
            ->select(
                'additional_costs.id',
                'users.username as requester',
                'additional_costs.transaction_number',
                'additional_costs.reference_number',
                'additional_costs.pr_number',
                'additional_costs.po_number',
                'fixed_assets.vladimir_tag_number as vladimir_tag_number',
                'additional_costs.asset_description',
                'additional_costs.asset_specification',
                'type_of_requests.type_of_request_name as type_of_request',
                'suppliers.supplier_name',
                'additional_costs.accountability',
                'additional_costs.accountable',
                'additional_costs.received_by',
                'additional_costs.cellphone_number',
                'additional_costs.brand',
                'additional_costs.receipt',
                'additional_costs.quantity',
                'additional_costs.acquisition_date',
                'additional_costs.acquisition_cost',
                DB::raw("NULL as remarks"),
                DB::raw("'Served' as status"),
                DB::raw('CONCAT(companies.company_code, " - ", companies.company_name) as company'),
                DB::raw('CONCAT(business_units.business_unit_code, " - ", business_units.business_unit_name) as business_unit'),
                DB::raw('CONCAT(sub_units.sub_unit_code, " - ", sub_units.sub_unit_name) as sub_unit'),
                DB::raw('CONCAT(departments.department_code, " - ", departments.department_name) as department'),
                DB::raw('CONCAT(locations.location_code, " - ", locations.location_name) as location'),
                DB::raw('CONCAT(account_titles.account_title_code, " - ", account_titles.account_title_name) as account_title'),
                'additional_costs.add_cost_sequence'

            )
            ->where('additional_costs.reference_number', $referenceNumber)
            ->get()
            ->map(function ($item) {
                $collectionName = Str::slug($item->received_by) . '-signature';
                $signature = $item->getFirstMedia($collectionName);
                $item->attachments = [
                    'signature' => $signature ? [
                        'id' => $signature->id,
                        'file_name' => $signature->file_name,
                        'file_path' => $signature->getPath(),
                        'file_url' => $signature->getUrl(),
                        'collection_name' => $signature->collection_name,
//                        'viewing' => $this->convertImageToBase64($signature->getPath()),
                    ] : null,
                ];
                unset($item->media); // Remove the 'media' property from the response
                return $item;
            });

        $assetRequest = AssetRequest::withTrashed()
            ->join('users', 'asset_requests.requester_id', '=', 'users.id')
            ->join('type_of_requests', 'asset_requests.type_of_request_id', '=', 'type_of_requests.id')
            ->join('suppliers', 'asset_requests.supplier_id', '=', 'suppliers.id')
            ->join('companies', 'asset_requests.company_id', '=', 'companies.id')
            ->join('business_units', 'asset_requests.business_unit_id', '=', 'business_units.id')
            ->join('sub_units', 'asset_requests.subunit_id', '=', 'sub_units.id')
            ->join('departments', 'asset_requests.department_id', '=', 'departments.id')
            ->join('locations', 'asset_requests.location_id', '=', 'locations.id')
            ->join('account_titles', 'asset_requests.account_title_id', '=', 'account_titles.id')
            ->select(
                'asset_requests.id',
                'users.username as requester',
                'asset_requests.transaction_number',
                'asset_requests.reference_number',
                'asset_requests.pr_number',
                DB::raw("'-' as po_number"),
                DB::raw("'-' as vladimir_tag_number"),
                'asset_requests.asset_description',
                'asset_requests.asset_specification',
                'type_of_requests.type_of_request_name as type_of_request',
                'suppliers.supplier_name as supplier',
                'asset_requests.accountability',
                'asset_requests.accountable',
                'asset_requests.received_by',
                'asset_requests.cellphone_number',
                'asset_requests.brand',
                DB::raw("'-' as receipt"),
                'asset_requests.quantity',
                'asset_requests.acquisition_date',
                'asset_requests.acquisition_cost',
                'asset_requests.remarks',
                DB::raw("'Cancelled' as status"),
                DB::raw('CONCAT(companies.company_code, " - ", companies.company_name) as company'),
                DB::raw('CONCAT(business_units.business_unit_code, " - ", business_units.business_unit_name) as business_unit'),
                DB::raw('CONCAT(sub_units.sub_unit_code, " - ", sub_units.sub_unit_name) as sub_unit'),
                DB::raw('CONCAT(departments.department_code, " - ", departments.department_name) as department'),
                DB::raw('CONCAT(locations.location_code, " - ", locations.location_name) as location'),
                DB::raw('CONCAT(account_titles.account_title_code, " - ", account_titles.account_title_name) as account_title'),
                DB::raw('NULL as add_cost_sequence')
            )
            ->where('asset_requests.reference_number', $referenceNumber)
            ->where('asset_requests.deleted_at', '!=', null)
            ->get()
            ->map(function ($item) {

                $letterOfRequestMedia = $item->getMedia('letter_of_request')->first();
                $quotationMedia = $item->getMedia('quotation')->first();
                $specificationFormMedia = $item->getMedia('specification_form')->first();
                $toolOfTradeMedia = $item->getMedia('tool_of_trade')->first();
                $otherAttachmentsMedia = $item->getMedia('other_attachments')->first();

                $item->attachments = [
                    'letter_of_request' => $letterOfRequestMedia ? [
                        'id' => $letterOfRequestMedia->id,
                        'file_name' => $letterOfRequestMedia->file_name,
                        'file_path' => $letterOfRequestMedia->getPath(),
                        'file_url' => $letterOfRequestMedia->getUrl(),
                    ] : null,
                    'quotation' => $quotationMedia ? [
                        'id' => $quotationMedia->id,
                        'file_name' => $quotationMedia->file_name,
                        'file_path' => $quotationMedia->getPath(),
                        'file_url' => $quotationMedia->getUrl(),
                    ] : null,
                    'specification_form' => $specificationFormMedia ? [
                        'id' => $specificationFormMedia->id,
                        'file_name' => $specificationFormMedia->file_name,
                        'file_path' => $specificationFormMedia->getPath(),
                        'file_url' => $specificationFormMedia->getUrl(),
                    ] : null,
                    'tool_of_trade' => $toolOfTradeMedia ? [
                        'id' => $toolOfTradeMedia->id,
                        'file_name' => $toolOfTradeMedia->file_name,
                        'file_path' => $toolOfTradeMedia->getPath(),
                        'file_url' => $toolOfTradeMedia->getUrl(),
                    ] : null,
                    'other_attachments' => $otherAttachmentsMedia ? [
                        'id' => $otherAttachmentsMedia->id,
                        'file_name' => $otherAttachmentsMedia->file_name,
                        'file_path' => $otherAttachmentsMedia->getPath(),
                        'file_url' => $otherAttachmentsMedia->getUrl(),
                    ] : null,
                ];
                unset($item->media); // Remove the 'media' property from the response
                return $item;
            });


//        if ($search !== null) {
//            $fixedAsset = $fixedAsset->where('fixed_assets.asset_description', 'like', '%' . $search . '%')
//                ->orWhere('vladimir_tag_number', 'like', '%' . $search . '%')
//                ->where('reference_number', $referenceNumber);
//            $additionalCost = $additionalCost->where('additional_costs.asset_description', 'like', '%' . $search . '%')
//                ->orWhere('fixed_assets.vladimir_tag_number', 'like', '%' . $search . '%')
//                ->where('additional_costs.reference_number', $referenceNumber);
//            $assetRequest = $assetRequest->where('asset_requests.asset_description', 'like', '%' . $search . '%')
//                ->where('asset_requests.reference_number', $referenceNumber);
//        }

//        $unionQuery = DB::query()->fromSub(function ($query) use ($fixedAsset, $additionalCost, $assetRequest) {
//            $query->fromSub($fixedAsset->unionAll($additionalCost)->unionAll($assetRequest), 'union_sub');
//        }, 'union_query');
        $unionQuery = $fixedAsset->concat($additionalCost)->concat($assetRequest);
        if (!in_array($user->role->role_name, $allowedRole)) {
            $unionQuery = $unionQuery->where('requester', $user->username);
        }

        if ($perPage !== null) {
            $page = request()->get('page', 1); // Get the current page or default to 1
            $offset = ($page * $perPage) - $perPage;

            $result = new LengthAwarePaginator(
                $unionQuery->slice($offset, $perPage)->values(),
                $unionQuery->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $result = $unionQuery->all();
        }

        if (empty($result)) {
            return $result = [];
        }

        return $result;
    }
}
