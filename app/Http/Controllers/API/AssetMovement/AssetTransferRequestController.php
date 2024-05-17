<?php

namespace App\Http\Controllers\API\AssetMovement;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetTransfer\CreateAssetTransferRequestRequest;
use App\Http\Requests\AssetTransfer\UpdateAssetTransferRequestRequest;
use App\Models\Approvers;
use App\Models\AssetMovementContainer\AssetTransferContainer;
use App\Models\AssetRequest;
use App\Models\AssetTransferApprover;
use App\Models\AssetTransferRequest;
use App\Models\TransferApproval;
use App\Models\User;
use App\Traits\AssetMovement\AssetTransferContainerHandler;
use App\Traits\AssetMovement\TransferRequestHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AssetTransferRequestController extends Controller
{
    use ApiResponse, TransferRequestHandler, AssetTransferContainerHandler;


    public function index(Request $request)
    {
//        return $this->approverChanged(12, new AssetTransferRequest(), new TransferApproval(), new AssetTransferApprover(), 'transfer_number');

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

        $transferRequest = AssetTransferRequest::query();

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }
        if ($status == 'deactivated') {
            $transferRequest->withTrashed();
        }
        if (!$forMonitoring) {
            $transferRequest->where('created_by_id', $requesterId);
        }

        $transferRequest = $transferRequest->orderByDesc('created_at')->useFilters();
        $transferRequest = $transferRequest
            ->get()
            ->groupBy('transfer_number')
            ->map(function ($transferCollection) use ($status) {
                $firstTransfer = $transferCollection->first();
                $allTrashed = $transferCollection->every->trashed();

                if (($status == 'deactivated' && $allTrashed) || ($status == 'active' && !$allTrashed)) {
                    // Count the number of items with the same transfer number and return the count
                    $firstTransfer->quantity = $transferCollection->count();
                    $firstTransfer->status = $firstTransfer->status == 'Approved' ?? $firstTransfer->is_fa_approved ? 'For Approval of FA' : $firstTransfer->status;
                    return $this->transformTransferRequest($firstTransfer);
                }
            })
            ->filter()
            ->values();


        if ($perPage !== null) {
            $page = $request->input('page', 1);
            $offset = $page * $perPage - $perPage;
            $transferRequest = new LengthAwarePaginator($transferRequest->slice($offset, $perPage)->values(), $transferRequest->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }


//        $test = AssetTransferRequest::get();
        return $transferRequest;
    }

    public function store(CreateAssetTransferRequestRequest $request)
    {

//        try {
//            DB::beginTransaction();
        $fixedAssetIds = $request->assets;
        $attachments = $request->attachments;
        $createdBy = auth('sanctum')->user()->id;
        $transferNumber = AssetTransferRequest::generateTransferNumber();
        $transferApproval = AssetTransferApprover::where('subunit_id', $request->subunit_id)
            ->orderBy('layer', 'asc')
            ->get();


        list($isRequesterApprover, $isLastApprover, $requesterLayer) = $this->checkIfRequesterIsApprover($createdBy, $transferApproval);

        foreach ($fixedAssetIds as $index => $fixedAssetId) {
            $transferRequest = AssetTransferRequest::create([
                'transfer_number' => $transferNumber,
                'status' => $isLastApprover
                    ? 'Approved'
                    : ($isRequesterApprover
                        ? 'For Approval of Approver ' . ($requesterLayer + 1)
                        : 'For Approval of Approver 1'),
                'created_by_id' => $createdBy,
                'fixed_asset_id' => $fixedAssetId['fixed_asset_id'],
                'accountability' => $request->accountability,
                'accountable' => $request->accountable,
                'company_id' => $request->company_id,
                'business_unit_id' => $request->business_unit_id,
                'department_id' => $request->department_id,
                'unit_id' => $request->unit_id,
                'subunit_id' => $request->subunit_id,
                'location_id' => $request->location_id,
                'remarks' => $request->remarks,
                'description' => $request->description,
            ]);

            // If this is the first iteration, add the attachments


            if ($index === 0 && $attachments) {
                $attachments = is_array($attachments) ? $attachments : [$attachments];
                foreach ($attachments as $attachment) {
                    foreach ($attachment as $file) {
                        $transferRequest->addMedia($file)->toMediaCollection('attachments');
                    }
//                        $transferRequest->addMedia($attachment)->toMediaCollection('attachments');
//                    $transferRequest->addMediaFromRequest($attachment)->toMediaCollection('attachments');
                }

            }
        }
        $this->setTransferApprovals($request->subunit_id, $createdBy, $transferRequest, new AssetTransferApprover(), new TransferApproval());

//            DB::commit();
        return $this->responseSuccess('Asset Transfer Request Created');
//        } catch (\Exception $e) {
//            DB::rollBack();
//            return $this->responseServerError($e);
//        }
    }

    public function show($transferNumber)
    {
        $transferRequest = AssetTransferRequest::where('transfer_number', $transferNumber)->get();
        return $this->setContainerResponse($transferRequest);
    }


    public function update(Request $request, $id)
    {
        //
    }

    public function updateTransfer(UpdateAssetTransferRequestRequest $request, $id)
    {
        //check if the status of the request is return or not yet approved by the first approver
        $transferRequest = AssetTransferRequest::withTrashed()->where('id', $id)->first();
        if ($transferRequest->deleted_at) {
            return $this->responseUnprocessable('Request not found');
        }

        $transferApprovals = TransferApproval::where('transfer_number', $transferRequest->transfer_number)->get();
        //check if the user is one of the approver if it is then check if the next approver to the user has approved the request if it is then return an error
        $user = $transferRequest->created_by_id;
        $isUserApprover = Approvers::where('approver_id', $user)->first()->id;
        $userApprover = $transferApprovals->where('approver_id', $isUserApprover)->first();
        if ($userApprover) {
            $userLayer = $userApprover->layer;
            $nextApprover = $transferApprovals->where('layer', $userLayer + 1)->first();
            if ($nextApprover->status == 'Approved') {
                return $this->responseUnprocessable('This request cannot be updated.');
            }
        }
//        return $transferRequest;
        $this->updateTransferRequest($transferRequest, $request);

        $isDataUpdated = $transferRequest->isDirty() ? 'true' : 'false';

        $transferRequest->save();

        $this->handleAttachment($transferRequest, $request);
        Cache::put('isDataUpdated', $isDataUpdated, 60);
        $this->approverChanged($id, new AssetTransferRequest(), new TransferApproval(), new AssetTransferApprover(), 'transfer_number');
        return $this->responseSuccess('Request updated Successfully');
    }

    public function removedTransferItem($transferNumber, $id = null)
    {
        $query = AssetTransferRequest::query();

        if ($id !== null) {
            $query->where('id', $id);
        }

        $assetTransfer = $query->orWhere('transfer_number', $transferNumber);

        if ($id !== null) {
            $assetTransfer = $assetTransfer->first();
        } else {
            $assetTransfer = $assetTransfer->get();
        }
        if (!$assetTransfer) {
            return $this->responseUnprocessable('Request not found');
        }
//        return $this->deleteRequestItem($id, 'transfer_number', new AssetTransferRequest(), new TransferApproval());

        if (!$id) {
//            $transferRequest = AssetTransferRequest::where('transfer_number', $transferNumber)->get();
//            $transferRequest->each->delete();
            return $this->deleteRequest($transferNumber, 'transfer_number', new AssetTransferRequest(), new TransferApproval());
//            return $this->responseSuccess('Request removed Successfully');
        } else {
            return $this->deleteRequestItem($id, 'transfer_number', new AssetTransferRequest(), new TransferApproval());
        }
    }

    public function transferContainerData()
    {
        $userId = auth('sanctum')->user()->id;

        $transferNumber = AssetTransferRequest::generateTransferNumber();

        //get the item from transfer container
        $transferContainer = AssetTransferContainer::where('created_by_id', $userId)->get();
        if (!$transferContainer) {
            return $this->responseUnprocessable('No item in the transfer container.');
        }

        foreach ($transferContainer as $item) {
            $assetTransfer = new AssetTransferRequest();

            $assetTransfer->transfer_number = $transferNumber;
            $assetTransfer->status = $item->status;
            $assetTransfer->created_by_id = $userId;
            $assetTransfer->fixed_asset_id = $item->fixed_asset_id;
            $assetTransfer->accountability = $item->accountability;
            $assetTransfer->accountable = $item->accountable;
            $assetTransfer->company_id = $item->company_id;
            $assetTransfer->business_unit_id = $item->business_unit_id;
            $assetTransfer->department_id = $item->department_id;
            $assetTransfer->unit_id = $item->unit_id;
            $assetTransfer->subunit_id = $item->subunit_id;
            $assetTransfer->location_id = $item->location_id;
            $assetTransfer->account_id = $item->account_id;
            $assetTransfer->remarks = $item->remarks;
            $assetTransfer->description = $item->description;

            $assetTransfer->save();

            if ($item->getMedia('attachments')) {
                $itemMedia = $item->getMedia('attachments');
                foreach ($itemMedia as $media) {
                    $media->copy($assetTransfer, 'attachments');
                }
            }
            $item->delete();
        }

        $this->setTransferApprovals($transferContainer, $userId, $assetTransfer, new AssetTransferApprover(), new TransferApproval());

        return $this->responseCreated('Asset Transfer Request Created');
    }

    public function transferAttachmentDl($transferNumber): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->dlAttachments($transferNumber, 'transfer_number', new AssetTransferRequest());
    }

    public function transferRequestAction(Request $request)
    {
        $action = $request->action;
        $transferNumber = $request->transfer_number;
        $remarks = $request->remarks;

        return $this->requestAction($action, $transferNumber, 'transfer_number', new AssetTransferRequest(), new TransferApproval(), $remarks);
    }

    public function transferApproverView()
    {

    }
}
