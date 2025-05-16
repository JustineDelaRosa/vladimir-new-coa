<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Support\LazyCollection;
use App\Http\Requests\AssetRequest\CreateAssetRequestRequest;
use App\Http\Requests\AssetRequest\UpdateAssetRequestRequest;
use App\Models\AdditionalCost;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\DepartmentUnitApprovers;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\RequestContainer;
use App\Models\RoleManagement;
use App\Models\User;
use App\Models\YmirPRItem;
use App\Models\YmirPRTransaction;
use App\Repositories\ApprovedRequestRepository;
use App\Traits\AssetRequestHandler;
use App\Traits\ItemDetailsHandler;
use App\Traits\RequestShowDataHandler;
use Carbon\Carbon;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

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
            'search' => ['nullable', 'string'], // Add search validation
        ]);

        $forMonitoring = $request->for_monitoring ?? 0;
        $warehouseMonitoring = $request->warehouse_monitoring ?? 0;
        $user = auth('sanctum')->user();
        $userWarehouse = $user->warehouse_id ?? null;
        $requesterId = $user->id;
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status', 'active');
        $filter = $request->input('filter', null);
        $claimedFilter = $request->input('claimed', null);
        $search = $request->input('search', null); // Get the search term

        if ($claimedFilter == 'Claimed') {
            $filter = 'Claimed';
        }
        if ($status == 'deactivated') {
            $filter = null;
        }

        $baseQuery = AssetRequest::select(
            'transaction_number',
            DB::raw('MAX(requester_id) as requester_id'),
            DB::raw('MAX(id) as id'),
            DB::raw('MAX(created_at) as created_at'),
            DB::raw('SUM(quantity) as total_quantity'),
            DB::raw('SUM(quantity_delivered) as total_quantity_delivered'),
            DB::raw('COUNT(*) as total_items'),
            DB::raw('SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as deleted_items')
        )
            ->when($forMonitoring != 1, function ($query) use ($requesterId) {
                $query->where('requester_id', $requesterId);
            })
            ->when($filter == "Returned", function ($query) {
                $query->where('status', 'like', 'Returned%');
            })
            ->when($filter == "For Approval", function ($query) {
                $query->where('status', 'like', 'For Approval%');
            })
            ->when($filter == "For FA Approval", function ($query) {
                $query->where('status', 'Approved')->where('is_fa_approved', 0);
            })
            ->when($filter == "Sent To Ymir", function ($query) {
                $query->where('status', 'Approved')->where('filter', 'Sent to Ymir');
            })
            ->when($filter == "For Tagging", function ($query) {
                $query->where('status', 'Approved')->where('filter', 'Asset Tagging');
            })
            ->when($filter == "For Pickup", function ($query) {
                $query->where('status', 'Approved')->where('filter', 'Ready to Pickup');
            })
            ->when($filter == "Claimed", function ($query) {
                $query->where('is_claimed', 1)->where('filter', 'Claimed');
            })
            ->when($warehouseMonitoring == 1, function ($query) use ($userWarehouse) {
                $query->where('receiving_warehouse_id', $userWarehouse);
            })
            ->when(!$filter && $status == 'active', function ($query) {
                $query->where(function ($q) {
                    $q->where('filter', '!=', 'Claimed')
                        ->orWhereRaw('quantity != quantity_delivered');
                });
            })
            ->when($status == 'deactivated', function ($query) {
                $query->withTrashed();
            })
            ->when($search, function ($query) use ($search) { // Add search condition
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_number', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('acquisition_details', 'like', "%{$search}%")
                        ->orWhere('pr_number', 'like', "%{$search}%")
                        ->orWhere('asset_description', 'like', "%{$search}%")
                        ->orWhere('asset_specification', 'like', "%{$search}%")
                        ->orWhereHas('requestor', function ($q) use ($search) {
                            $q->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('employee_id', 'like', "%{$search}%");
                        })->orWhereHas('receivingWarehouse', function ($q) use ($search) {
                            $q->where('warehouse_name', 'like', "%{$search}%");
                        });
                });
            })
            ->groupBy('transaction_number');

        if ($status == 'deactivated') {
            $baseQuery->havingRaw('deleted_items = total_items');
        } else {
            $baseQuery->havingRaw('deleted_items < total_items');
        }

        $total = DB::table(DB::raw("({$baseQuery->toSql()}) as count_table"))
            ->mergeBindings($baseQuery->getQuery())
            ->count();

        $paginatedQuery = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->useFilters()
            ->skip(($request->input('page', 1) - 1) * $perPage)
            ->take($perPage);

        $transactionData = $paginatedQuery->get();

        $ids = $transactionData->pluck('id')->filter()->toArray();

        $assetRequests = AssetRequest::when($status == 'deactivated', function ($q) {
            $q->withTrashed();
        })
            ->with(['requestor', 'company:id,company_name', 'department:id,department_name'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $result = $transactionData->map(function ($item) use ($assetRequests) {
            if (!isset($assetRequests[$item->id])) {
                return null;
            }

            $assetRequest = $assetRequests[$item->id];
            $assetRequest->quantity = $item->total_quantity;
            $assetRequest->quantity_delivered = $item->total_quantity_delivered;
            return $this->transformIndexAssetRequest($assetRequest);
        })->filter()->values();

        return new LengthAwarePaginator(
            $result,
            $total,
            $perPage,
            $request->input('page', 1),
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }


//    public function indexOld(Request $request)
//    {
////        return AssetRequest::with('fixedAssetTransactionNumber')->get();
//
////        return YmirPRTransaction::get();
//
//        $request->validate([
//            'for_monitoring' => 'boolean',
//            'filter' => ['nullable', 'string'],
//        ]);
//
//        $forMonitoring = $request->for_monitoring ?? false;
//        $user = auth('sanctum')->user();
//
//        $requesterId = $user->id;
////        $role = Cache::remember("user_role_$requesterId", 60, function () use ($requesterId) {
////            return User::find($requesterId)->roleManagement->role_name;
////        });
////        // $role = User::find($requesterId)->roleManagement->role_name;
////        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
//
//        $perPage = $request->input('per_page', null);
//        $status = $request->input('status', 'active');
//        $filter = $request->input('filter', null);
////        $filter = $filter ? explode(',', $filter) : [];
////        $filter = array_map('trim', $filter);
//        $claimedFilter = $request->input('claimed', null);
//
//        $conditions = [
//            'Returned' => ['status' => ['like', 'Returned%']],
//            'For Approval' => ['status' => ['like', 'For Approval%']],
//            'For FA Approval' => ['status' => 'Approved', 'is_fa_approved' => 0],
//            'Sent To Ymir' => ['status' => 'Approved', 'filter' => 'Sent to Ymir'],
//            'For Tagging' => ['status' => 'Approved', 'filter' => 'Asset Tagging'],//'filter' => 'Ready to Pickup'
//            'For Pickup' => ['status' => 'Approved', 'filter' => 'Ready to Pickup'],
//            'Claimed' => ['is_claimed' => 1, 'filter' => 'Claimed'],
//        ];
//
//        if ($claimedFilter == 'Claimed') {
//            $filter = 'Claimed';
//        }
//
//        $assetRequest = AssetRequest::query();
//
////        if (!in_array($role, $adminRoles)) {
////            $forMonitoring = false;
////        }
//        if ($status == 'deactivated') {
//            $assetRequest->withTrashed();
//        }
//
//        if (!$forMonitoring) {
//            $assetRequest->where('requester_id', $requesterId);
//        }
//
////        if (!empty($filter)) {
////            $assetRequest->where(function ($query) use ($filter, $conditions) {
////                foreach ($filter as $key) {
////                    if (isset($conditions[$key])) {
////                        $query->orWhere(function ($query) use ($conditions, $key) {
////                            foreach ($conditions[$key] as $field => $value) {
////                                if (is_array($value)) {
////                                    $query->where($field, $value[0], $value[1]);
////                                } else {
////                                    $query->where($field, $value);
////                                }
////                            }
////                        });
////                    }
////                }
////            });
////        }
//
//        if ($filter) {
//            $assetRequest->when($filter == "Returned", function ($query) {
//                $query->where('status', 'like', 'Returned%');
//            })->when($filter == "For Approval", function ($query) {
//                $query->where('status', 'like', 'For Approval%');
//            })->when($filter == "For FA Approval", function ($query) {
//                $query->where('status', 'Approved')->where('is_fa_approved', 0);
//            })->when($filter == "Sent To Ymir", function ($query) {
//                $query->where('status', 'Approved')->where('filter', 'Sent to Ymir');
//            })->when($filter == "For Tagging", function ($query) {
//                $query->where('status', 'Approved')->where('filter', 'Asset Tagging');
//            })->when($filter == "For Pickup", function ($query) {
//                $query->where('status', 'Approved')->where('filter', 'Ready to Pickup');
//            })->when($filter == "Claimed", function ($query) {
//                $query->where('is_claimed', 1)->where('filter', 'Claimed');
//            });
//        } else {
//            $assetRequest->where(function ($query) {
//                $query->where('filter', '!=', 'Claimed')
//                    ->orWhereRaw('quantity != quantity_delivered');
//            });
//        }
//
//
//        $assetRequest = $assetRequest->orderByDesc('created_at')->useFilters();
//        $assetRequest = $assetRequest
//            ->get()
//            ->groupBy('transaction_number')
//            ->map(function ($assetRequestCollection) use ($filter, $status, $claimedFilter) {
//                // If 'Deleted' filter is active and all items in the group are trashed, include the group in the result
//                //                if (in_array('Deleted', $filter) && $assetRequestCollection->every->trashed()) {
//                //                    $assetRequest = $assetRequestCollection->first();
//                //                    $assetRequest->quantity = $assetRequestCollection->sum('quantity');
//                //                    return $this->transformIndexAssetRequest($assetRequest);
//                //                }
//                // If status is 'deactivated', check if all items in the group are trashed
//                if ($status == 'deactivated' && $assetRequestCollection->every->trashed()) {
//                    $assetRequest = $assetRequestCollection->first();
//                    $assetRequest->quantity = $assetRequestCollection->sum('quantity');
//                    $assetRequest->quantity_delivered = $assetRequestCollection->sum('quantity_delivered');
//                    //add all the quantity of soft deleted asset request
//                    /*                    $cancelled = AssetRequest::onlyTrashed()->where('transaction_number', $assetRequest->transaction_number)->sum('quantity');
//                                        $anyRecentlyUpdated = $assetRequestCollection->contains(function ($item) {
//                                            return $item->updated_at->diffInMinutes(now()) < 2;
//                                        });
//                                        $assetRequest->cancelled = $cancelled;*/
//
//                    return $this->transformIndexAssetRequest($assetRequest);
//                } // If status is 'active', check if any item in the group is not trashed id all is trash return null
//                else if ($status == 'active' && !$assetRequestCollection->every->trashed()) {
//                    $assetRequest = $assetRequestCollection->first();
//                    $assetRequest->quantity = $assetRequestCollection->sum('quantity');
//                    $assetRequest->quantity_delivered = $assetRequestCollection->sum('quantity_delivered');
//
//                    // Exclude the item if quantity and quantity_delivered are equal
//                    /*                    if (($assetRequest->quantity == $assetRequest->quantity_delivered) && $assetRequest->filter == 'Claimed' && $claimedFilter !== 'claimed') {
//                                            return null;
//                                        }*/
//
//                    //add all the quantity of soft deleted asset request
//                    /*                    $cancelled = AssetRequest::onlyTrashed()->where('transaction_number', $assetRequest->transaction_number)->sum('quantity');
//                                        $anyRecentlyUpdated = $assetRequestCollection->contains(function ($item) {
//                                            return $item->updated_at->diffInMinutes(now()) < 2;
//                                        });
//                                        $assetRequest->cancelled = $cancelled;*/
//                    return $this->transformIndexAssetRequest($assetRequest);
//                }
//            })
//            ->filter()
//            ->values();
//
//        if ($perPage !== null) {
//            $page = $request->input('page', 1);
//            $offset = $page * $perPage - $perPage;
//            $assetRequest = new LengthAwarePaginator($assetRequest->slice($offset, $perPage)->values(), $assetRequest->count(), $perPage, $page, [
//                'path' => $request->url(),
//                'query' => $request->query(),
//            ]);
//        }
//        return $assetRequest;
//    }


    /*    public function indexRevamp(Request $request)
        {
    //        return AssetRequest::with('fixedAssetTransactionNumber')->get();

    //        return YmirPRTransaction::get();

            $request->validate([
                'for_monitoring' => 'boolean',
                'filter' => ['nullable', 'string'],
            ]);

            $forMonitoring = $request->for_monitoring ?? false;
            $user = auth('sanctum')->user();

            $requesterId = $user->id;
    //        $role = Cache::remember("user_role_$requesterId", 60, function () use ($requesterId) {
    //            return User::find($requesterId)->roleManagement->role_name;
    //        });
    //        // $role = User::find($requesterId)->roleManagement->role_name;
    //        $adminRoles = ['Super Admin', 'Admin', 'ERP'];

            $perPage = $request->input('per_page', null);
            $status = $request->input('status', 'active');
            $filter = $request->input('filter', null);
    //        $filter = $filter ? explode(',', $filter) : [];
    //        $filter = array_map('trim', $filter);
            $claimedFilter = $request->input('claimed', null);

            $conditions = [
                'Returned' => ['status' => ['like', 'Returned%']],
                'For Approval' => ['status' => ['like', 'For Approval%']],
                'For FA Approval' => ['status' => 'Approved', 'is_fa_approved' => 0],
                'Sent To Ymir' => ['status' => 'Approved', 'filter' => 'Sent to Ymir'],
                'For Tagging' => ['status' => 'Approved', 'filter' => 'Asset Tagging'],//'filter' => 'Ready to Pickup'
                'For Pickup' => ['status' => 'Approved', 'filter' => 'Ready to Pickup'],
                'Released' => ['is_claimed' => 1, 'filter' => 'Claimed'],
            ];

    //        if ($claimedFilter == 'claimed') {
    //            $filter[] = 'Released';
    //        }

            // Group and aggregate in the database query
            $query = AssetRequest::select([
                'transaction_number',
                DB::raw('MAX(status) as status'),
                DB::raw('MAX(filter) as filter'),
                DB::raw('MAX(is_claimed) as is_claimed'),
                DB::raw('MAX(is_fa_approved) as is_fa_approved'),
                DB::raw('MAX(created_at) as created_at'),
                DB::raw('MAX(updated_at) as updated_at'),
                DB::raw('MAX(requester_id) as requester_id'),
                DB::raw('MAX(company_id) as company_id'),
                DB::raw('MAX(business_unit_id) as business_unit_id'),
                DB::raw('MAX(department_id) as department_id'),
                DB::raw('MAX(unit_id) as unit_id'),
                DB::raw('MAX(subunit_id) as subunit_id'),
                DB::raw('MAX(location_id) as location_id'),
                DB::raw('MAX(major_category_id) as major_category_id'),
                DB::raw('MAX(minor_category_id) as minor_category_id'),
                DB::raw('MAX(uom_id) as uom_id'),
                DB::raw('MAX(type_of_request_id) as type_of_request_id'),
                DB::raw('MAX(supplier_id) as supplier_id'),
                DB::raw('MAX(fixed_asset_id) as fixed_asset_id'),
                DB::raw('MAX(account_title_id) as account_title_id'),
                DB::raw('MAX(accountable) as accountable'),
                DB::raw('MAX(asset_description) as asset_description'),
                DB::raw('MAX(asset_specification) as asset_specification'),
                DB::raw('MAX(cellphone_number) as cellphone_number'),
                DB::raw('MAX(brand) as brand'),
                DB::raw('MAX(receiving_warehouse_id) as receiving_warehouse_id'),
                DB::raw('MAX(pr_number) as pr_number'),
                DB::raw('MAX(is_addcost) as is_addcost'),
                DB::raw('MAX(attachment_type) as attachment_type'),
                DB::raw('MAX(additional_info) as additional_info'),
                DB::raw('MAX(acquisition_details) as acquisition_details'),
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('SUM(quantity_delivered) as quantity_delivered'),
                DB::raw('MIN(id) as id')
            ])
                ->with([
                    'requestor:id,username,employee_id,firstname,lastname',
                    'company:id,company_code,company_name',
                    'businessUnit:id,business_unit_code,business_unit_name',
                    'department:id,department_code,department_name,sync_id',
                    'unit:id,unit_code,unit_name',
                    'subunit:id,sub_unit_code,sub_unit_name',
                    'location:id,location_code,location_name',
                    'majorCategory:id,major_category_name',
                    'minorCategory.initialDebit:id,sync_id,account_title_code,account_title_name',
                    'minorCategory.depreciationCredit:id,sync_id,credit_code,credit_name',
                    'uom:id,uom_code,uom_name',
                    'typeOfRequest:id,type_of_request_name',
                    'supplier:id,supplier_code,supplier_name',
                    'fixedAsset',
                    'assetApproval'
                ])
                ->groupBy('transaction_number')
                ->orderByDesc('created_at')
                ->useFilters();

            // Handle filters and status conditions in the query
            if ($status == 'deactivated') {
                $query->withTrashed()->whereRaw('EXISTS (
            SELECT 1 FROM asset_requests
            WHERE transaction_number = asset_requests.transaction_number
            AND deleted_at IS NOT NULL
        )');
            } else {
                // For active status, exclude where all are trashed
                $query->whereRaw('EXISTS (
            SELECT 1 FROM asset_requests
            WHERE transaction_number = asset_requests.transaction_number
            AND deleted_at IS NULL
        )');
            }

            // Apply $filter filter for claimed items
            if ($claimedFilter != 'Claimed') {
                $query->where(function ($q) {
                    $q->where('filter', '!=', 'Claimed')
                        ->WhereRaw('quantity != quantity_delivered');
                })->whereNull('deleted_at');
            } else {
                $filter = 'Claimed';
            }

            if ($filter) {
                $query->when($filter == 'Returned', function ($q) {
                    $q->where('status', 'like', 'Returned%');
                })->when($filter == 'For Approval', function ($q) {
                    $q->where('status', 'like', 'For Approval%');
                })->when($filter == 'For FA Approval', function ($q) {
                    $q->where('status', 'Approved')->where('is_fa_approved', 0);
                })->when($filter == 'Sent To Ymir', function ($q) {
                    $q->where('status', 'Approved')->where('filter', 'Sent to Ymir');
                })->when($filter == 'For Tagging', function ($q) {
                    $q->where('status', 'Approved')->where('filter', 'Asset Tagging');
                })->when($filter == 'For Pickup', function ($q) {
                    $q->where('status', 'Approved')->where('filter', 'Ready to Pickup');
                })->when($filter == 'Claimed', function ($q) {
                    $q->where('is_claimed', 1)->where('filter', 'Claimed');
                });
            }else{
                $query->where(function ($q) {
                    $q->where('filter', '!=', 'Claimed')
                        ->orWhereRaw('quantity != quantity_delivered');
                });
            }


            // Add this before the map function
            $cancelledCounts = AssetRequest::onlyTrashed()
                ->select('transaction_number', DB::raw('SUM(quantity) as cancelled_count'))
                ->groupBy('transaction_number')
                ->pluck('cancelled_count', 'transaction_number')
                ->toArray();


            if ($perPage !== null) {
                // Get the total count for pagination
                $total = $query->toBase()->getCountForPagination();

                // Get the current page
                $page = $request->input('page', 1);

                // Apply pagination limits to the database query itself
                $items = $query->forPage($page, $perPage)->get();

                // Apply the transformation
                $transformedItems = collect($items)->map(function ($item) use ($cancelledCounts) {
                    $item->cancelled = $cancelledCounts[$item->transaction_number] ?? 0;
                    return $this->transformIndexAssetRequest($item);
                })->values(); // Use values() to re-index the collection

                // Create paginator with correctly indexed items
                $assetRequests = new LengthAwarePaginator(
                    $transformedItems,
                    $total,
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
            } else {
                // No pagination needed
                $assetRequests = $query->get()->map(function ($item) use ($cancelledCounts) {
                    $item->cancelled = $cancelledCounts[$item->transaction_number] ?? 0;
                    return $this->transformIndexAssetRequest($item);
                });
            }
            return $assetRequests; // Fix the variable name here as well
        }*/


    public function show($transactionNumber)
    {
        $user = auth('sanctum')->user();
//        $isAdmin = in_array($user->role->role_name, ['Admin', 'Super Admin']);

        $query = AssetRequest::with([
            'requestor:id,username,employee_id,firstname,lastname',
            'company:id,company_code,company_name',
            'businessUnit:id,business_unit_code,business_unit_name',
            'department:id,department_code,department_name,sync_id',
            'unit:id,unit_code,unit_name',
            'subunit:id,sub_unit_code,sub_unit_name',
            'location:id,location_code,location_name',
            'majorCategory:id,major_category_name',
            'minorCategory.initialDebit:id,sync_id,account_title_code,account_title_name',
            'minorCategory.depreciationCredit:id,sync_id,credit_code,credit_name',
            'uom:id,uom_code,uom_name',
            'typeOfRequest:id,type_of_request_name',
            'supplier:id,supplier_code,supplier_name',
            'fixedAsset',
            'assetApproval'
        ])->where('transaction_number', $transactionNumber);

        // Add requester filter for non-admins
//        if (!$isAdmin) {
//            $query->where('requester_id', $user->id);
//        }

        // Get total and soft deleted count for the transaction
        $totalCount = AssetRequest::withTrashed()
            ->where('transaction_number', $transactionNumber)
            ->count();

        $deletedCount = AssetRequest::onlyTrashed()
            ->where('transaction_number', $transactionNumber)
            ->count();

        // If all items are soft deleted, include them in results
        if ($totalCount === $deletedCount) {
            $query->withTrashed();
        } else {
            // If not all items are deleted, only show non-deleted items
            $query->whereNull('deleted_at');
        }

        // Optimize ordering
        $query->orderByRaw('ISNULL(deleted_at) DESC, created_at DESC');

        // Get paginated results
        $assetRequests = $query->dynamicPaginate();

        if ($assetRequests->isEmpty()) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        // Transform data using the trait
        return $this->responseData($assetRequests);
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
        $isFileDataUpdated = Cache::get('isFileDataUpdated', false);
        $isDataUpdated = Cache::get('isDataUpdated', false);
        $transactionNumber = $request->transaction_number;
        if ($isDataUpdated || $isFileDataUpdated) {
            $this->approveRequestRepository->resubmitRequest($transactionNumber);
            Cache::forget('isDataUpdated');
            Cache::forget('isFileDataUpdated');
            return $this->responseSuccess('AssetRequest resubmitted Successfully');
        } else {
            return $this->responseUnprocessable('No changes, need to update first.');
//            return $this->responseUnprocessable('You can\'t resubmit this request.');
        }
    }

    public function resubmitAssetRequest($transactionNumber): JsonResponse
    {
        $isFileDataUpdated = Cache::get('isFileDataUpdated', false);
        $isDataUpdated = Cache::get('isDataUpdated', false);
        if ($isDataUpdated || $isFileDataUpdated) {
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
        DB::beginTransaction();

        try {
            $transactionNumber = AssetRequest::where('reference_number', $referenceNumber)->first()->transaction_number ?? null;
            $request->merge([
                'receiving_warehouse_id' => Department::where('id', $request->department_id)->pluck('receiving_warehouse_id')->first(),
                'is_addcost' => $request->item_id ? 1 : 0,
            ]);

            $assetRequest = $this->getAssetRequestForApprover('reference_number', $transactionNumber, $referenceNumber);
//            return $assetRequest;
            if (!$assetRequest) {
                return $this->responseUnprocessable('Asset Request not found.');
            }

            // Make changes to the $assetRequest and $ar objects but don't save them
            $assetRequest = $this->updateAssetRequest($assetRequest, $request, $save = false);
            // Check if the $assetRequest and $ar objects are dirty
            $isDataUpdated = (bool)$assetRequest->isDirty();

            // Save the changes to the $assetRequest and $ar objects
            $assetRequest->accountingEntries()->update([
                'initial_debit' => $request->initial_debit_id,
                'depreciation_credit' => $request->depreciation_credit_id,
            ]);
            $assetRequest->save();

            $this->handleMediaAttachments($assetRequest, $request);

            //TODO: Make this last for only 20 mins if there is bug
            Cache::put('isDataUpdated', $isDataUpdated, now()->addMinutes(5));
            $this->approveRequestRepository->isApproverChange($transactionNumber, $isDataUpdated);

            if (Cache::get('isDataUpdated') || Cache::get('isFileDataUpdated')) {
                $this->resubmitAssetRequest($transactionNumber);
//                $this->invalidateCache();
            }

            DB::commit();

            return $this->responseSuccess('AssetRequest updated Successfully', [
                'isDataUpdates' => Cache::get('isDataUpdated') || Cache::get('isFileDataUpdated') ? 1 : 0,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
//            return $e->getMessage();
            return $this->responseUnprocessable('Something went wrong, please try again.');
        }
    }

    public function removeRequestItem($transactionNumber, $referenceNumber = null)
    {

        if ($transactionNumber && $referenceNumber) {
            //            return 'both';
            $item = $this->deleteRequestItem($referenceNumber, $transactionNumber);
            if (isset($item['error'])) {
                return $this->responseUnprocessable($item['message']);
            }
            if (Cache::get('isDataUpdated')) {
                $this->resubmitAssetRequest($transactionNumber);
            }
            return $this->responseSuccess('AssetRequest Item removed Successfully');
        }
        if ($transactionNumber) {
            //            return 'single';
            return $this->deleteAssetRequest($transactionNumber);
        }
    }


    public function moveData()
    {
        $requesterId = auth('sanctum')->user()->id;
        $transactionNumber = AssetRequest::generateTransactionNumber();

        $items = RequestContainer::where('requester_id', $requesterId)->get();
        if ($items->isEmpty()) {
            return $this->responseUnprocessable('No data to move');
        }

        $subunitId = $items->first()->subunit_id;
        if (!DepartmentUnitApprovers::where('subunit_id', $subunitId)->exists()) {
            return $this->responseUnprocessable('No approver found for this subunit');
        }

        if ($items->pluck('subunit_id')->unique()->count() > 1) {
            return $this->responseUnprocessable('Invalid Action, Different Subunit');
        }

        $assetRequests = $items->map(function ($item) use ($transactionNumber) {
            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];
            $assetRequest = new AssetRequest($item->only([
                'status', 'is_addcost', 'item_status', 'item_id', 'fixed_asset_id', 'requester_id', 'type_of_request_id', 'attachment_type',
                'additional_info', 'acquisition_details', 'accountability', 'major_category_id', 'minor_category_id',
                'uom_id', 'company_id', 'business_unit_id', 'department_id', 'unit_id', 'subunit_id', 'location_id',
                'account_title_id', 'accountable', 'asset_description', 'asset_specification', 'cellphone_number', 'brand',
                'quantity', 'date_needed', 'receiving_warehouse_id'
            ]));
            $assetRequest->transaction_number = $transactionNumber;
            $assetRequest->reference_number = $assetRequest->generateReferenceNumber();
            $assetRequest->save();

            foreach ($fileKeys as $fileKey) {
                $item->getMedia($fileKey)->each->copy($assetRequest, $fileKey);
            }

            $item->forceDelete();
            return $assetRequest;
        });

        $this->createAssetApprovals($items, $requesterId, $assetRequests->last());

        $this->requestedLog($transactionNumber);

        return $this->responseSuccess('Successfully requested');
    }

    public function requestedLog($transactionNumber)
    {
        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        activity()
            ->causedBy($user)
            ->performedOn($assetRequests)
            ->inLog('Requested')
            ->tap(function ($activity) use ($transactionNumber) {
                $activity->subject_id = $transactionNumber;
            })
            ->log('Requested Asset Request with Transaction Number: ' . $transactionNumber);
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
//        $allowedRole = ['Super Admin', 'Admin', 'ERP'];


        $fixedAsset = $this->getFAItemDetails($referenceNumber);
        $additionalCost = $this->getACItemDetails($referenceNumber);
        $assetRequest = $this->getARItemDetails($referenceNumber);

        $unionQuery = $fixedAsset->concat($additionalCost)->concat($assetRequest);
//        if (!in_array($user->role->role_name, $allowedRole)) {
//            $unionQuery = $unionQuery->where('requester', $user->username);
//        }

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

    /*    public function store(CreateAssetRequestRequest $request): JsonResponse
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
        }*/


    //OLD MOVE DATA

    //    public function moveData()
//    {
//        // Get the requester id from the request
//        $requesterId = auth('sanctum')->user()->id;
//        $transactionNumber = AssetRequest::generateTransactionNumber();
//
//        // Get the items from Request-container
//        $items = RequestContainer::where('requester_id', $requesterId)->get();
////        return $items;
//        if ($items->isEmpty()) {
//            return $this->responseUnprocessable('No data to move');
//        }
//        //check if the item inside item has different subunit id
//        $subunitId = $items[0]->subunit_id;
//        $hasApproverCheck = DepartmentUnitApprovers::where('subunit_id', $subunitId)->exists();
//        if (!$hasApproverCheck) {
//            return $this->responseUnprocessable('No approver found for this subunit');
//        }
//        foreach ($items as $item) {
//            if ($item->subunit_id != $subunitId) {
//                return $this->responseUnprocessable('Invalid Action, Different Subunit');
//            }
//        }
//        $assetRequest = null;
//        foreach ($items as $item) {
//            $assetRequest = new AssetRequest();
//            $assetRequest->status = $item->status;
//            $assetRequest->is_addcost = $item->is_addcost;
//            $assetRequest->fixed_asset_id = $item->fixed_asset_id;
//            $assetRequest->requester_id = $item->requester_id;
//            $assetRequest->type_of_request_id = $item->type_of_request_id;
//            $assetRequest->attachment_type = $item->attachment_type;
//            $assetRequest->additional_info = $item->additional_info;
//            $assetRequest->acquisition_details = $item->acquisition_details;
//            $assetRequest->accountability = $item->accountability;
//            $assetRequest->major_category_id = $item->major_category_id;
//            $assetRequest->minor_category_id = $item->minor_category_id;
//            $assetRequest->uom_id = $item->uom_id;
//            $assetRequest->company_id = $item->company_id;
//            $assetRequest->business_unit_id = $item->business_unit_id;
//            $assetRequest->department_id = $item->department_id;
//            $assetRequest->unit_id = $item->unit_id;
//            $assetRequest->subunit_id = $item->subunit_id;
//            $assetRequest->location_id = $item->location_id;
//            $assetRequest->account_title_id = $item->account_title_id;
//            $assetRequest->accountable = $item->accountable;
//            $assetRequest->asset_description = $item->asset_description;
//            $assetRequest->asset_specification = $item->asset_specification;
//            $assetRequest->cellphone_number = $item->cellphone_number;
//            $assetRequest->brand = $item->brand;
//            $assetRequest->quantity = $item->quantity;
//            $assetRequest->date_needed = $item->date_needed;
//            $assetRequest->receiving_warehouse_id = $item->receiving_warehouse_id;
//
//            // Add transaction number and reference number
//            $assetRequest->transaction_number = $transactionNumber;
//            $assetRequest->reference_number = $assetRequest->generateReferenceNumber();
//
//            $assetRequest->save();
//            // $assetRequest->reference_number = str_pad($assetRequest->id, 4, '0', STR_PAD_LEFT);
//            // $assetRequest->save();
//
//            // Get the media from RequestContainer and put it in AssetRequest
//            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];
//
//            foreach ($fileKeys as $fileKey) {
//                $media = $item->getMedia($fileKey);
//                foreach ($media as $file) {
//                    $file->copy($assetRequest, $fileKey);
//                }
//            }
//
//            // Delete the item from RequestContainer
//            $item->forceDelete();
//        }
//
//         $this->createAssetApprovals($items, $requesterId, $assetRequest);
//
//        return $this->responseSuccess('Successfully requested');
//    }


    public function exportAging(Request $request)
    {
        $user = auth('sanctum')->user();
        $userWarehouse = $user->warehouse_id ?? null;
        $from = $request->input('from');
        $to = $request->input('to');
        $dataAll = $request->input('data_all', 0);
        $exportMonitoring = $request->input('export_monitoring', 0);
        //check if the user is admin then allow the dataAll to be 1 else 0
//        if ($user->role->role_name !== 'Admin') {
//            $dataAll = 0;
//        }
        $from = $from ? Carbon::parse($from)->startOfDay() : null;
        $to = $to ? Carbon::parse($to)->endOfDay() : null;


//        $status = $request->input('status');


        $assetRequest = AssetRequest::when($from && $to, function ($query) use ($from, $to) {
            return $query->whereBetween('created_at', [$from, $to]);
        })
            ->when($exportMonitoring, function ($query) use ($userWarehouse) {
                return $query->where('receiving_warehouse_id', $userWarehouse);
            })
            ->when($from && !$to, function ($query) use ($from) {
                return $query->where('created_at', '>=', $from);
            })
            ->when(!$from && $to, function ($query) use ($to) {
                return $query->where('created_at', '<=', $to);
            })
            ->when($dataAll == 0, function ($query) use ($user) {
                return $query->where('requester_id', $user->id);
            })
            ->get();

        $data = $assetRequest->map(function ($item) {

            //compute the the days from created_at to the last activity log created at date
            $lastActivity = Activity::whereSubjectType(AssetRequest::class)
                ->whereSubjectId($item->transaction_number)
                ->latest()->first()->created_at;
            $created = $item->created_at;
            $days = $created->diffInDays($lastActivity);

            try {
                $YmirPRNumber = YmirPRTransaction::where('pr_number', $item->pr_number)->first()->pr_year_number_id ?? null;
            } catch (\Exception $e) {
                $YmirPRNumber = $item->pr_number;
            }

            $isAllDeleted = AssetRequest::withTrashed()->where('transaction_number', $item->transaction_number)->count() == AssetRequest::onlyTrashed()->where('transaction_number', $item->transaction_number)->count();

            $requestStatus = 'For Approval of FA';

            if (strpos($item->status, 'For Approval') === 0) {
                $requestStatus = $item->status;
            } elseif ($item->status == 'Returned') {
                $requestStatus = $item->status;
            } elseif ($item->is_fa_approved) {
                $requestStatus = $item->filter == 'Sent to Ymir' ? 'Sent to ymir for PO' : $item->filter;
            } elseif ($isAllDeleted) {
                $requestStatus = 'Cancelled';
            } elseif ($item->is_fa_approved) {
                $requestStatus = $item->status;
            }


            return [
                'transaction_number' => $item->transaction_number,
//                'reference_number' => $item->reference_number,
                'requester' => $item->requestor->username,
                'receiving_warehouse' => $item->receivingWarehouse->warehouse_name,
                'ymir_pr_number' => $YmirPRNumber,
                'pr_number' => $item->pr_number ?? '-',
                'po_number' => $item->po_number ?? '-',
                'rr_number' => $item->rr_number ?? '-',
                'item_status' => $item->item_status,
                'acquisition_details' => $item->acquisition_details,
                'quantity' => $item->quantity,
                'delivered' => $item->quantity_delivered ?? '-',
                'remaining' => $item->quantity - $item->quantity_delivered ?? '-',
                'cancelled' => (int)$item->onlyTrashed()->where('transaction_number', $item->transaction_number)->sum('quantity'),
                'company_name' => $item->company->company_name,
                'company_code' => $item->company->company_code,
                'business_unit_name' => $item->businessUnit->business_unit_name,
                'business_unit_code' => $item->businessUnit->business_unit_code,
                'department_name' => $item->department->department_name,
                'department_code' => $item->department->department_code,
                'unit_name' => $item->unit->unit_name,
                'unit_code' => $item->unit->unit_code,
                'sub_unit_name' => $item->subUnit->sub_unit_name,
                'sub_unit_code' => $item->subUnit->sub_unit_code,
                'location_name' => $item->location->location_name,
                'location_code' => $item->location->location_code,
                'status' => $requestStatus,
                'created_at' => $item->created_at,
                'aging' => $days
            ];
        });

        return $data;

    }
}
