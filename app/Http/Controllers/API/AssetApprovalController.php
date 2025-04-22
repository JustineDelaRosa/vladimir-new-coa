<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetApproval\CreateAssetApprovalRequest;
use App\Http\Requests\AssetApproval\UpdateAssetApprovalRequest;
use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\MinorCategory;
use App\Models\RoleManagement;
use App\Models\User;
use App\Repositories\ApprovedRequestRepository;
use App\Traits\AssetApprovalHandler;
use App\Traits\AssetRequestHandler;
use App\Traits\RequestShowDataHandler;
use App\Traits\ReusableFunctions\Reusables;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AssetApprovalController extends Controller
{
    use ApiResponse, AssetApprovalHandler, assetRequestHandler, RequestShowDataHandler, Reusables;

    private ApprovedRequestRepository $approveRequestRepository;

    public function __construct(ApprovedRequestRepository $approveRequestRepository)
    {
        $this->approveRequestRepository = $approveRequestRepository;
    }

    public function index(Request $request)
    {
        $request->validate([
            'for_monitoring' => 'boolean',
            'status' => 'string|in:For Approval,Approved', // or any other statuses you might have
        ]);

        $forMonitoring = $request->for_monitoring ?? false;
        $perPage = $request->input('per_page', null);
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->value('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $status = $request->input('status', 'For Approval');
        $finalApproval = $request->input('final_approval', false);

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        $assetApprovalsQuery = AssetApproval::query();
        if (!$forMonitoring) {
            $assetApprovalsQuery->where('approver_id', $approverId)->get();
        }
        $transactionNumbers = [];
        if ($this->isUserFa()) {
            $transactionNumbers = AssetRequest::where('status', 'Approved')
                ->when($status === 'Approved', function ($query) {
                    return $query->where('is_fa_approved', true);
                }, function ($query) {
                    return $query->where('is_fa_approved', false);
                })
                ->pluck('transaction_number');
        }

        $assetApprovals = $assetApprovalsQuery->where('status', $status)->get();

        if ($finalApproval) {
            $transactionNumbers = is_array($transactionNumbers) ? $transactionNumbers : [$transactionNumbers];
        } else {
            $transactionNumbers = $assetApprovals->pluck('transaction_number')->toArray();
        }

//        $transactionNumbers = array_merge($transactionNumbers, $assetApprovals->pluck('transaction_number')->toArray());
        $transactionNumbers = Arr::flatten($transactionNumbers);

        $data = AssetRequest::with('assetApproval', 'assetApproval.approver', 'assetApproval.approver.user')
            ->whereIn('transaction_number', $transactionNumbers)
            ->useFilters()
            ->get()
            ->groupBy('transaction_number')
            ->map(function ($assetRequests) {
                $assetRequest = $assetRequests->first();
                $assetRequest->quantity = $assetRequests->sum('quantity');
                return $this->approverViewing($assetRequest->transaction_number);
            })
            ->values();

        if ($perPage !== null) {
            $data = $this->paginateApproval($request, $data->toArray(), $perPage);
            $data->setCollection($data->getCollection()->values());
        }

        return $data;

//        $transactionNumbers = $assetApprovals->map(function ($item) {
        //            return $item->transaction_number;
        //        });

//        return $this->transformIndexApproval($assetApprovals, $transactionNumbers);
    }

    public function store(CreateAssetApprovalRequest $request): JsonResponse
    {
        $assetApproval = AssetApproval::create($request->all());
        return $this->responseCreated('AssetApproval created successfully', $assetApproval);
    }

    public function show($transactionNumber)
    {
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->value('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $assetApprovalQuery = AssetApproval::query();
        if (!in_array($role, $adminRoles)) {
            if (!$this->isUserFa()) {
                $assetApprovalQuery->where('approver_id', $approverId);
            }
        }

        // if ($this->isUserFa()) {
        //     $assetApprovalQuery->where('status', 'Approved');
        // }

        $assetApproval = $assetApprovalQuery->where('transaction_number', $transactionNumber)->first();
        if (!$assetApproval) {
            return $this->responseUnprocessable('Unauthorized Access');
        }
        $assetRequest = AssetRequest::where('transaction_number', $assetApproval->transaction_number)
            ->dynamicPaginate();
        return $this->responseData($assetRequest);
    }

    public function update(UpdateAssetApprovalRequest $request, AssetApproval $assetApproval): JsonResponse
    {
        $assetApproval->update($request->all());
        return $this->responseSuccess('AssetApproval updated Successfully', $assetApproval);
    }

    public function destroy(AssetApproval $assetApproval): JsonResponse
    {
        $assetApproval->delete();
        return $this->responseDeleted();
    }

    public function handleRequest(CreateAssetApprovalRequest $request)
    {
//        $assetApprovalIds = $request->asset_approval_id;
        //$assetRequestIds = $request->asset_request_id;
        $transactionNumber = $request->transaction_number;
        $remarks = $request->remarks;
        $action = ucwords($request->action);

//        $hasPR = AssetRequest::where('transaction_number', $transactionNumber)->value('pr_number');
//        if(!$hasPR){
//            return $this->responseUnprocessable('Pr generation failed, please try again');
//        }
//        return $this->responseSuccess('Successfully Approved');
        return $this->requestAction($action, $transactionNumber, 'transaction_number', new AssetRequest(), new AssetApproval(), $remarks);

//        switch ($action) {
        //            case 'Approve':
        //                return $this->approveRequestRepository->approveRequest($assetApprovalIds);
        //                break;
        //            case 'Return':
        //                return $this->approveRequestRepository->returnRequest($assetApprovalIds, $remarks);
        //                break;
        //            default:
        //                return $this->responseUnprocessable('Invalid Action');
        //                break;
        //        }
    }

    //TODO: To be adjust because of FA Approval
    public function getNextRequest(Request $request)
    {
        $user = auth('sanctum')->user();
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $status = 'For Approval';
        $finalApproval = $request->final_approval ?? false;
        $isUserFa = $this->isUserFa();

        // Get transaction numbers based on user role
        $transactionNumbers = $this->getTransactionNumbersForUser(
            $isUserFa,
            $approverId,
            $status,
            $finalApproval
        );

        // If no transaction numbers found, return early
        if (empty($transactionNumbers)) {
            return $this->responseNotFound('No request found');
        }

        // Get and format asset requests
        $data = $this->getFormattedAssetRequests($transactionNumbers);

        if ($data->isEmpty()) {
            return $this->responseNotFound('No request found');
        }

        return $data;
    }

    private function getTransactionNumbersForUser($isUserFa, $approverId, $status, $finalApproval)
    {
        // Initialize empty array
        $transactionNumbers = [];

        // Get transaction numbers for FA approvers
        if ($isUserFa && $finalApproval) {
            $transactionNumbers = AssetRequest::where('status', 'Approved')
                ->where('is_fa_approved', false)
                ->pluck('transaction_number')
                ->toArray();
        }

        // If not in final approval mode or no FA transactions found, get from asset approvals
//        || empty($transactionNumbers)
        if (!$finalApproval) {
            $transactionNumbers = AssetApproval::where('approver_id', $approverId)
                ->where('status', $status)
                ->pluck('transaction_number')
                ->toArray();
        }

        return Arr::flatten($transactionNumbers);
    }

    private function getFormattedAssetRequests($transactionNumbers)
    {
        return AssetRequest::with('assetApproval', 'assetApproval.approver', 'assetApproval.approver.user')
            ->whereIn('transaction_number', $transactionNumbers)
            ->get()
            ->groupBy('transaction_number')
            ->map(function ($assetRequests) {
                return $this->responseData($assetRequests);
            })
            ->flatten(1)
            ->values();
    }


    public function paginateApproval($request, $data, $perPage)
    {
        $page = $request->input('page', 1);
        $offset = ($page * $perPage) - $perPage;
        return new LengthAwarePaginator(
            array_slice($data, $offset, $perPage, true),
            count($data),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }


    public function finalApprovalUpdate(Request $request, $referenceNumber)
    {
        DB::beginTransaction();
        try {
            if (!$this->isUserFa()) {
                return $this->responseUnprocessable('You are not authorized to perform this action');
            }

            $majorCategory = $request->major_category_id;
            $minorCategory = $request->minor_category_id;
            $description = $request->description;
            $assetRequest = AssetRequest::where('reference_number', $referenceNumber)
                ->where('status', 'Approved')
                ->where('is_fa_approved', false)
                ->first();

            if (!$assetRequest) {
                return $this->responseNotFound('Request not found');
            }

            $oldMinorCategory = $assetRequest->minor_category_id;
            $isSameCategory = $oldMinorCategory == $minorCategory;
            $assetRequest->update([
                'major_category_id' => $majorCategory,
                'minor_category_id' => $minorCategory,
                'asset_description' => $description,
            ]);
            $newMinorCategory = MinorCategory::where('id', $minorCategory)->first();
            if (!$isSameCategory) {
                //update the accounting entries
                $accountingEntries = $assetRequest->accountingEntries;
                $accountingEntries->update([
                    'initial_debit' => $newMinorCategory->initialDebit->sync_id,
                    'depreciation_credit' => $newMinorCategory->depreciationCredit->sync_id,
                ]);
            }

            $this->logFinalApproverUpdate($assetRequest, $request, $oldMinorCategory);

            DB::commit();
            return $this->responseSuccess('Final approval update successful');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('Failed to update: ' . $e->getMessage());
        }
    }

    private function logFinalApproverUpdate($assetRequest, $request, $oldMinorCategory)
    {
        $user = auth('sanctum')->user();
        $aRequest = new AssetRequest();
        $minorCategory = MinorCategory::where('id', $request->minor_category_id)->first();
        activity()
            ->causedBy($user)
            ->performedOn($aRequest)
            ->withProperties([
                'action' => 'FA Update',
                'reference_number' => $assetRequest->reference_number,
                'description' => $assetRequest->description,
                'old_minor_category' => $minorCategory->minor_category_name,
            ])
            ->inLog(ucwords('FA Update'))
            ->tap(function ($activity) use ($assetRequest) {
                $activity->subject_id = $assetRequest->transaction_number;
            })
            ->log('FA Update the Request');
    }
}
