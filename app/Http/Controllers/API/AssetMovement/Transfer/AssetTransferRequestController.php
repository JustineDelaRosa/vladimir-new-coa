<?php

namespace App\Http\Controllers\API\AssetMovement\Transfer;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetTransfer\CreateAssetTransferRequestRequest;
use App\Http\Requests\AssetTransfer\UpdateAssetTransferRequestRequest;
use App\Models\Approvers;
use App\Models\AssetMovementContainer\AssetTransferContainer;
use App\Models\AssetTransferApprover;
use App\Models\AssetTransferRequest;
use App\Models\MovementApproval;
use App\Models\MovementNumber;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use App\Traits\AssetMovement\AssetTransferContainerHandler;
use App\Traits\AssetMovement\TransferRequestHandler;
use App\Traits\RequestShowDataHandler;
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
//return $request->all();
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

        $transferRequest = $transferRequest->orderByDesc('created_at')->useFilters()->get();
        $transferRequest = $transferRequest
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
        try {
            DB::beginTransaction();
            $fixedAssetIds = $request->assets;
            $attachments = $request->file('attachments');
            $requesterSubUnit = auth('sanctum')->user()->subunit_id;
            $createdBy = auth('sanctum')->user()->id;
            $transferNumber = AssetTransferRequest::generateTransferNumber();
            $transferApproval = AssetTransferApprover::where('subunit_id', $requesterSubUnit)
                ->orderBy('layer', 'asc')
                ->get();
            if($transferApproval->isEmpty()){
                return $this->responseUnprocessable('No approver found for you, please contact support');
            }

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
//                    'account_id' => $request->account_id,
                    'remarks' => $request->remarks,
                    'description' => $request->description,
                ]);

                // If this is the first iteration, add the attachments
                if ($index === 0 && $attachments) {
                    foreach ($attachments as $attachment) {
                        $attachments = is_array($attachment) ? $attachment : [$attachment];
                        $transferRequest->addMedia($attachment)->toMediaCollection('attachments');
//                        $transferRequest->addMedia($attachment)->toMediaCollection('attachments');
//                    $transferRequest->addMediaFromRequest($attachment)->toMediaCollection('attachments');
                    }
                }
            }
            $this->setTransferApprovals($requesterSubUnit, $createdBy, $transferRequest, new AssetTransferApprover(), new TransferApproval());

            DB::commit();
            return $this->responseSuccess('Asset Transfer Request Created');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseServerError($e);
        }
    }

    public function show($transferNumber)
    {
        $transferRequests = AssetTransferRequest::withTrashed()->where('transfer_number', $transferNumber)
            ->orderByDesc('created_at')
            ->useFilters()
            ->get();

        $groupedTransferRequests = $transferRequests
            ->groupBy('transfer_number')
            ->map(function ($transferCollection) {
                $firstTransfer = $transferCollection->first();
                $attachments = $firstTransfer->first()->getMedia('attachments')->all();
                return [
                    'transfer_number' => $firstTransfer->transfer_number,
                    'assets' => $transferCollection->whereNull('deleted_at')->map(function ($transfer) {
                        return $this->transformSingleFixedAssetShowData($transfer->fixedAsset);
//                        return [
//                            'id' => $transfer->fixedAsset->id,
//                            'vladimir_tag_number' => $this->transformSingleFixedAssetShowData($transfer->fixedAsset),
//                            'asset_description' => $transfer->fixedAsset->asset_description,
//                            'asset_specification' => $transfer->fixedAsset->asset_specification,
//                            'brand' => $transfer->fixedAsset->brand,
//                            'accountability' => $transfer->fixedAsset->accountability,
//                            'accountable' => $transfer->fixedAsset->accountable ?? '-',
//                            'quantity' => $transfer->fixedAsset->quantity,
//                            'created_at' => $transfer->fixedAsset->created_at
//                        ];
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

        return $groupedTransferRequests;
    }


    public function update(Request $request, $id)
    {
        //
    }

    public function updateTransfer(UpdateAssetTransferRequestRequest $request, $transferNumber)
    {
        $tagNumbers = $request->assets;
        $createdBy = auth('sanctum')->user()->id;
        $userSubUnit = auth('sanctum')->user()->subunit_id;
        $transferApproval = $this->getTransferApproval($userSubUnit);
        if($transferApproval->isEmpty()){
            return $this->responseUnprocessable('No approver found for you, please contact support');
        }

        foreach ($tagNumbers as $tagNumber) {
            $transferRequest = $this->getTransferRequest($transferNumber, $tagNumber['fixed_asset_id']);

            if ($transferRequest && $transferRequest->trashed()) {
                $transferRequest->restore();
            } elseif (!$transferRequest) {
                $this->createTransferRequest($tagNumber, $transferNumber, $request, $createdBy, $transferApproval);
            }

            if ($transferRequest) {
                $this->updateTransferRequest($transferRequest, $tagNumber, $request);
            }
        }

        $this->deleteNonExistingTransfers($transferNumber, $tagNumbers);
        $this->handleAttachment($transferRequest, $request);
        $this->approverChanged($transferNumber, new AssetTransferRequest(), new TransferApproval(), new AssetTransferApprover(), 'transfer_number');

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

}
