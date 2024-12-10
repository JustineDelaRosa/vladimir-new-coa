<?php

namespace App\Http\Controllers\API\AssetMovement\Transfer;

use App\Http\Controllers\Controller;
use App\Models\Approvers;
use App\Models\AssetTransferRequest;
use App\Models\RoleManagement;
use App\Models\TransferApproval;
use App\Models\User;
use App\Traits\AssetMovement\AssetTransferApprovalHandler;
use App\Traits\AssetMovement\AssetTransferContainerHandler;
use App\Traits\AssetMovement\TransferRequestHandler;
use App\Traits\ReusableFunctions\Reusables;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class AssetTransferApprovalController extends Controller
{
    use ApiResponse, AssetTransferContainerHandler, AssetTransferApprovalHandler, Reusables;

    public function index(Request $request)
    {

        $request->validate([
            'for_monitoring' => 'boolean',
            'status' => 'string|in:For Approval,Approved',
        ]);

        $forMonitoring = $request->for_monitoring ?? false;
        $perPage = $request->input('per_page', null);
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->pluck('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $status = $request->input('status', 'For Approval');

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        $transferApprovalQuery = TransferApproval::query();

        if (!$forMonitoring) {
            $transferApprovalQuery->where('approver_id', $approverId);
        }
        $transferNumbers = [];

        if ($this->isUserFa()) {
            $transferNumbers = AssetTransferRequest::where('status', 'Approved')
                ->when($status == 'Approved', function ($query) {
                    return $query->where('is_fa_approved', true);
                }, function ($query) {
                    return $query->where('is_fa_approved', false);
                })
                ->pluck('transfer_number');
        }

        $transferApproval = $transferApprovalQuery->where('status', $status)->get();

        $transferNumbers = is_array($transferNumbers) ? $transferNumbers : [$transferNumbers];
        $transferNumbers = array_merge($transferNumbers, $transferApproval->pluck('transfer_number')->toArray());
        $transferNumbers = Arr::flatten($transferNumbers);

        $data = AssetTransferRequest::with('transferApproval', 'transferApproval.approver', 'transferApproval.approver.user')
            ->whereIn('transfer_number', $transferNumbers)
            ->useFilters()
            ->get()
            ->groupBy('transfer_number')
            ->map(function ($transferRequest){
                $transferRequests = $transferRequest->first();
                return $this->approverViewing($transferRequests->transfer_number);
            })->values();

        if ($perPage !== null) {
            $data = $this->paginate($request, $data->toArray(), $perPage);
            $data->setCollection($data->getCollection()->values());
        }
        return $data;
    }

    public function show($transferNumber)
    {
        $transferRequest = AssetTransferRequest::where('transfer_number', $transferNumber)->get();
        return $this->setContainerResponse($transferRequest);
    }


    public function transferRequestAction(Request $request): \Illuminate\Http\JsonResponse
    {
        $action = $request->action;
        $transferNumber = $request->transfer_number;
        $remarks = $request->remarks;

        return $this->requestAction($action, $transferNumber, 'transfer_number', new AssetTransferRequest(), new TransferApproval(), $remarks);
    }


    //TODO: To be adjust because of FA Approval
    public function getNextRequest()
    {
        $user = auth('sanctum')->user();
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $transfer = TransferApproval::where('approver_id', $approverId)
            ->where('status', 'For Approval')
            ->oldest()
            ->first();
        if (!$transfer) {
            return $this->responseNotFound('No Request Found');
        }
        $assetRequest = AssetTransferRequest::where('transfer_number', $transfer->transfer_number)->get();
        return $this->responseData($assetRequest);
    }

    public function getNextTransferRequest()
    {
        $user = auth('sanctum')->user();
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $status = 'For Approval'; // or any other status you want to check

        // Check if the user is a FA approver
        $isUserFa = $this->isUserFa();

        $transferApprovalsQuery = TransferApproval::where('approver_id', $approverId);

        $transactionNumbers = [];
        if ($isUserFa) {
            $transactionNumbers = AssetTransferRequest::where('status', 'Approved')
                ->when($status == 'Approved', function ($query) {
                    return $query->where('is_fa_approved', true);
                }, function ($query) {
                    return $query->where('is_fa_approved', false);
                })
                ->pluck('transfer_number');
        }

        $transferApprovals = $transferApprovalsQuery->where('status', $status)->get();
        $transactionNumbers = is_array($transactionNumbers) ? $transactionNumbers : [$transactionNumbers];
        $transactionNumbers = array_merge($transactionNumbers, $transferApprovals->pluck('transfer_number')->toArray());
        $transactionNumbers = Arr::flatten($transactionNumbers);

        $data = AssetTransferRequest::with('transferApproval', 'transferApproval.approver', 'transferApproval.approver.user')
            ->whereIn('transfer_number', $transactionNumbers)
            ->get()
            ->groupBy('transfer_number')
            ->map(function ($transferCollection) {
                $firstTransfer = $transferCollection->first();
                $attachments = $firstTransfer->first()->getMedia('attachments')->all();
                return [
                    'transfer_number' => $firstTransfer->transfer_number,
                    'assets' => $transferCollection->whereNull('deleted_at')->map(function ($transfer) {
                        return $this->transformSingleFixedAssetShowData($transfer->fixedAsset);

                    })->values(),
                    'accountability' => $firstTransfer->accountability,
                    'accountable' => $firstTransfer->accountable,
                    'company' => [
                        'id' => $firstTransfer->company->id ?? '-',
                        'company_code' => $firstTransfer->company->company_code ?? '-',
                        'company_name' => $firstTransfer->company->company_name ?? '-',
                    ],
                    'description' => $firstTransfer->description,
                    'business_unit' => [
                        'id' => $firstTransfer->businessUnit->id ?? '-',
                        'business_unit_code' => $firstTransfer->businessUnit->business_unit_code ?? '-',
                        'business_unit_name' => $firstTransfer->businessUnit->business_unit_name ?? '-',
                    ],
                    'department' => [
                        'id' => $firstTransfer->department->id ?? '-',
                        'department_code' => $firstTransfer->department->department_code ?? '-',
                        'department_name' => $firstTransfer->department->department_name ?? '-',
                    ],
                    'unit' => [
                        'id' => $firstTransfer->unit->id ?? '-',
                        'unit_code' => $firstTransfer->unit->unit_code ?? '-',
                        'unit_name' => $firstTransfer->unit->unit_name ?? '-',
                    ],
                    'subunit' => [
                        'id' => $firstTransfer->subunit->id ?? '-',
                        'subunit_code' => $firstTransfer->subunit->sub_unit_code ?? '-',
                        'subunit_name' => $firstTransfer->subunit->sub_unit_name ?? '-',
                    ],
                    'location' => [
                        'id' => $firstTransfer->location->id ?? '-',
                        'location_code' => $firstTransfer->location->location_code ?? '-',
                        'location_name' => $firstTransfer->location->location_name ?? '-',
                    ],
                    'account_title' => [
                        'id' => $firstTransfer->accountTitle->id ?? '-',
                        'account_title_code' => $firstTransfer->accountTitle->account_title_code ?? '-',
                        'account_title_name' => $firstTransfer->accountTitle->account_title_name ?? '-',
                    ],
                    'created_at' => $firstTransfer->created_at,
                    'attachments' => $attachments ? collect($attachments)->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'name' => $attachment->file_name,
                            'url' => $attachment->getUrl(),
                        ];
                    }) : collect([]),
                ];
            })
            ->filter()
            ->values();

        return $data;
    }
}
