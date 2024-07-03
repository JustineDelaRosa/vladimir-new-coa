<?php

namespace App\Traits\AssetMovement;

use App\Models\Approvers;
use App\Models\AssetMovementHistory;
use App\Models\AssetTransferApprover;
use App\Models\AssetTransferRequest;
use App\Models\FixedAsset;
use App\Models\RoleManagement;
use App\Models\TransferApproval;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

trait TransferRequestHandler
{
    public function setTransferApprovals($items, $createdById, $assetTransfer, $subunitApproverModel, $approvalModel)
    {
        $subunitId = $items[0]->subunit_id ?? $items ?? null;

        $transferUnitApprovals = $subunitApproverModel::where('subunit_id', $subunitId)
            ->orderBy('layer', 'asc')
            ->get();
        $layerIds = $transferUnitApprovals->map(function ($item) {
            return $item->approver->approver_id;
        })->toArray();
        $isRequesterApprover = in_array($createdById, $layerIds);
        $requesterLayer = array_search($createdById, $layerIds) + 1;
//        return 'okay';
        foreach ($transferUnitApprovals as $approval) {
            $approverId = $approval->approver_id;
            $layer = $approval->layer;

            $status = null;

            if ($isRequesterApprover) {
                if ($layer == $requesterLayer || $layer < $requesterLayer) {
                    $status = "Approved";
                } elseif ($layer == $requesterLayer + 1) {
                    $status = "For Approval";
                }
            } elseif ($layer == 1) { // if the requester is not an approver, only the first layer should be "For Approval"
                $status = "For Approval";
            }

            $approvalModel::create([
                'transfer_number' => $assetTransfer->transfer_number,
                'approver_id' => $approverId,
                'requester_id' => $createdById,
                'layer' => $layer,
                'status' => $status,
            ]);
        }
    }

    public function transformTransferRequest($transferRequest)
    {
        return [
            'transfer_number' => $transferRequest->transfer_number,
            'description' => $transferRequest->description,

            'quantity' => $transferRequest->quantity,
            'current_approver' => $this->getCurrentApprover($transferRequest),
            'process_count' => $this->getProcessCount($transferRequest),
            'steps' => $this->setSteps($transferRequest),
            'history' => Activity::whereSubjectType(AssetTransferRequest::class)
                ->whereSubjectId($transferRequest->transfer_number)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'action' => $activity->log_name,
                        'causer' => $activity->causer,
                        'description' => $activity->properties['description'],
                        'vladimir_tag_number' => $activity->properties['vladimir_tag_number'],
                        'remarks' => $activity->properties['remarks'],
                        'created_at' => $activity->created_at,
                    ];
                }),
            'requester' => [
                'id' => $transferRequest->createdBy->id,
                'first_name' => $transferRequest->createdBy->firstname,
                'last_name' => $transferRequest->createdBy->lastname,
                'employee_id' => $transferRequest->createdBy->employee_id,
                'username' => $transferRequest->createdBy->username,
            ],
            'status' => $transferRequest->is_fa_approved ? 'Approved' : $transferRequest->status,
            'company' => [
                'id' => $transferRequest->company->id ?? '-',
                'company_code' => $transferRequest->company->company_code ?? '-',
                'company_name' => $transferRequest->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $transferRequest->businessUnit->id ?? '-',
                'business_unit_code' => $transferRequest->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $transferRequest->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $transferRequest->department->id ?? '-',
                'department_code' => $transferRequest->department->department_code ?? '-',
                'department_name' => $transferRequest->department->department_name ?? '-',
            ],
            'unit' => [
                'id' => $transferRequest->unit->id ?? '-',
                'unit_code' => $transferRequest->unit->unit_code ?? '-',
                'unit_name' => $transferRequest->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $transferRequest->subunit->id ?? '-',
                'subunit_code' => $transferRequest->subunit->sub_unit_code ?? '-',
                'subunit_name' => $transferRequest->subunit->sub_unit_name ?? '-',
            ],
            'location' => [
                'id' => $transferRequest->location->id ?? '-',
                'location_code' => $transferRequest->location->location_code ?? '-',
                'location_name' => $transferRequest->location->location_name ?? '-',
            ],
            'created_at' => $transferRequest->created_at,
        ];
    }

//    public function updateTransferRequest($transferRequest, $request)
//    {
//        $transferRequest->fill([
//            'fixed_asset_id' => $request->fixed_asset_id,
//            'accountable' => $request->accountable,
//            'company_id' => $request->company_id,
//            'business_unit_id' => $request->business_unit_id,
//            'department_id' => $request->department_id,
//            'unit_id' => $request->unit_id,
//            'subunit_id' => $request->subunit_id,
//            'location_id' => $request->location_id,
//            'remarks' => $request->remarks,
//            'description' => $request->description,
//        ]);
//
//        $this->updateAllAsset($transferRequest, $request);
//        return $request;
//    }

    public function updateAllAsset($transferRequest, $request)
    {

        $allRequest = AssetTransferRequest::where('transfer_number', $transferRequest->transfer_number)->get();
//        $tr= null;
        foreach ($allRequest as $tr) {
            $tr->fill([
                'fixed_asset_id' => $request->fixed_asset_id,
//                'accountable' => $request->accountable,
                'company_id' => $request->company_id,
                'business_unit_id' => $request->business_unit_id,
                'department_id' => $request->department_id,
                'unit_id' => $request->unit_id,
                'subunit_id' => $request->subunit_id,
                'location_id' => $request->location_id,
//                'remarks' => $request->remarks,
                'description' => $request->description,
            ]);
            $tr->save();
        }

        return;
    }

    public function handleAttachment($transferRequest, $request)
    {

        if (isset($request->attachments)) {
            $transferRequest->clearMediaCollection('attachments');
            foreach ($request->attachments as $attachment) {
                $transferRequest->addMedia($attachment)->toMediaCollection('attachments');
            }
        } else {
            $transferRequest->clearMediaCollection('attachments');
        }
    }

    public function approverChanged($uniqueNumberValue, $modelName, $approvalModelName, $subunitApprovalModelName, $uniqueNumber)
    {
        $user = auth('sanctum')->user()->id;
        $modelRequest = $modelName::where($uniqueNumber, $uniqueNumberValue)->first();
        $subunitId = $modelRequest->subunit_id;
        $subunitApproverIds = $subunitApprovalModelName::where('subunit_id', $subunitId)->pluck('approver_id')->toArray();
        $approverIds = $approvalModelName::where('transfer_number', $uniqueNumberValue)->pluck('approver_id')->toArray();
        if (array_diff($subunitApproverIds, $approverIds) || array_diff($approverIds, $subunitApproverIds) || $approverIds != $subunitApproverIds) {
            $approvalModelName::where('transfer_number', $uniqueNumberValue)->delete();
//            return $uniqueNumberValue;
            $items = $modelName::where('transfer_number', $uniqueNumberValue)->get();

            $this->setTransferApprovals($items, $user, $modelRequest, $subunitApprovalModelName, $approvalModelName);
        }
//        return 'notdiff';
        $firstLayer = $this->firstLayer($uniqueNumberValue, $approvalModelName);

        $currentApprovalLayer = $approvalModelName::where("$uniqueNumber", $uniqueNumberValue)
            ->where('status', '!=', 'Void')
            ->where('layer', $firstLayer)->first();

        if (!$currentApprovalLayer || $currentApprovalLayer->requester_id != $user) {
            return $this->responseUnprocessable('Invalid Action');
        }

        if ($modelRequest->status == 'Returned') {
            $this->updateTransferStatus($approvalModelName, $modelName, 'Returned');
        } else {
            $this->setToNull($uniqueNumber, $uniqueNumberValue, $approvalModelName);
            $this->setRequestStatus($uniqueNumber, $uniqueNumberValue, $modelName, 'For Approval of Approver ' . ($currentApprovalLayer->layer));
            $this->updateTransferStatus($approvalModelName, $modelName, 'For Approval');
        }
        return $this->responseSuccess('Request updated Successfully');
    }

    public function firstLayer($transactionNumber, $approvalModelName)
    {
        $userId = auth('sanctum')->user()->id;

        $transferApproval = $approvalModelName->where('transfer_number', $transactionNumber)
            ->where('approver_id', function ($query) use ($userId) {
                $query->select('id')
                    ->from((new Approvers)->getTable())
                    ->where('approver_id', $userId)
                    ->limit(1);
            })->first();

        return $transferApproval ? $transferApproval->layer + 1 : 1;
    }

    public function updateTransferStatus($approvalModel, $model, $status = null)
    {
        $user = auth('sanctum')->user()->id;

        $approvalModel->update(['status' => $status]);
        if ($approvalModel->first()->requester_id !== $user) {
            if ($status != 'For Approval') {
                $this->logActivity($approvalModel, $approvalModel->transfer_number, $model, $status);
            }
        }
    }

    public function setToNull($uniqueNumber, $uniqueNumberValue, $approvalModelName, $status = null)
    {
        $approvalModels = $approvalModelName::where($uniqueNumber, $uniqueNumberValue)->get();
        if ($status == 'Void') {
            $approvalModels->each->update(['status' => 'Void']);
        } else {
            $layer = $this->firstLayer($uniqueNumberValue, $approvalModelName);
            $approvalModels->where('layer', '>', $layer)->each->update(['status' => null]);
        }
    }

    public function setRequestStatus($uniqueNumber, $uniqueNumberValue, $modelName, $status, $remarks = null)
    {
        $modelName = $modelName::withTrashed()->where("$uniqueNumber", $uniqueNumberValue)->get();
        if ($status == 'Returned') {
            $modelName->each->update(['status' => $status, 'remarks' => $remarks]);
        } else {
            $modelName->each->update(['status' => $status]);
        }
    }

    public function logActivity($approvalModel, $transfer_number, $model, $status)
    {
//        return $transfer_number;
        $user = auth('sanctum')->user();
        activity()
            ->causedBy($user)
            ->performedOn($model)
            ->withProperties($this->setActivityLogProperties($status, $approvalModel, $transfer_number))
            ->tap(function ($activity) use ($transfer_number) {
                $activity->subject_id = $transfer_number;
            })
            ->inLog('Updated')
            ->log('Updated');

    }

    public function setActivityLogProperties($status, $approvalModel, $transfer_number)
    {
        return [
            'status' => $status,
            'transfer_number' => $transfer_number,
        ];
    }


    public function deleteRequestItem($id, $uniqueNumber, $model, $approvalModelName)
    {
        //check if the user is in approver table
        $user = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $user)->first()->id;
        $uniqueNumberValue = $model::withTrashed()->where('id', $id)->first()->$uniqueNumber;
        $nextApprover = $this->getNextApprover($approvalModelName, $uniqueNumber, $uniqueNumberValue, $approverId);
        $requestItem = $model::withTrashed()->where('id', $id)->first();

        //check if this item is the last item that is not yet deleted
        $lastItem = $model::where($uniqueNumber, $uniqueNumberValue)->get();
        $lastItem = $lastItem->filter(function ($item) {
            return !$item->deleted_at;
        });
        if (!$nextApprover) {
            //if the status is not For Approval of approver 1 or Returned then give an error
            if ($requestItem->status !== 'For Approval of Approver 1' && $requestItem->status !== 'Returned') {
                return $this->responseUnprocessable('Item cannot be deleted');
            }
        }

        if ($nextApprover && $nextApprover->status !== 'For Approval' && $nextApprover->status !== 'Returned') {
            return $this->responseUnprocessable('Item cannot be deleted');
        }

        //check if the item is already deleted
        if ($requestItem->deleted_at) {
            return $this->responseUnprocessable('Item already deleted');
        }

//        $requestItem->update([
//            'deleted_by' => $user,
//            'filter' => null
//        ]);
        //delete the request item
        $requestItem->update(['status' => 'Cancelled']);
        if ($lastItem->count() == 1) {
            $approvalModelName::where($uniqueNumber, $uniqueNumberValue)->update(['status' => 'Cancelled']);
        }
        $this->assetMovementLogger($requestItem->first(), 'Cancelled', $model);
        $requestItem->delete();
        $cookie = cookie('is_changed', true);
        return $this->responseSuccess('Item successfully deleted')->withCookie($cookie);
    }


    public function deleteRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName)
    {

        //check if the user is in approvers table
        $user = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $user)->first()->id;
        $nextApprover = $this->getNextApprover($approvalModelName, $uniqueNumber, $uniqueNumberValue, $approverId);
        $requestItem = $model::where($uniqueNumber, $uniqueNumberValue)->get();


        if (!$nextApprover) {
            //if status is not For Approval of approver 1 or Returned then give an error
            if ($requestItem->status !== 'For Approval of Approver 1' && $requestItem->status !== 'Returned') {
                return $this->responseUnprocessable('Item cannot be deleted');
            }
        }
        if ($nextApprover->status !== 'For Approval' && $nextApprover->status !== 'Returned') {
            return $this->responseUnprocessable('Item cannot be deleted');
        }

        //check if the item is already deleted
//        if ($requestItem->deleted_at) {
//            return $this->responseUnprocessable('Item already deleted');
//        }

//        $requestItem->update([
//            'deleted_by' => $user,
//            'filter' => null
//        ]);
        //delete the request item
        //change the status firsts to cancelled
        $requestItem->each->update(['status' => 'Cancelled']);
        $approvalModelName::where($uniqueNumber, $uniqueNumberValue)->update(['status' => 'Cancelled']);
        $this->assetMovementLogger($requestItem->first(), 'Cancelled', $model);
        $requestItem->each->delete();
        $cookie = cookie('is_changed', true);
        return $this->responseSuccess('Item successfully deleted')->withCookie($cookie);
    }


    public function getNextApprover($approvalModelName, $uniqueNumber, $uniqueNumberValue, $approverId)
    {
        $lastUserToApproved = $approvalModelName::where($uniqueNumber, $uniqueNumberValue)
            ->where('approver_id', $approverId)
            ->first();

        if (!$lastUserToApproved) {
            // approverId is not in the list
            // You can return null or throw an exception here
            return null;
        }

        $nextItem = $approvalModelName::where($uniqueNumber, $uniqueNumberValue)
            ->skip($lastUserToApproved->layer)
            ->take(1)
            ->first();

        return $nextItem;
    }

    public function dlAttachments($uniqueNumberValue, $uniqueNumber, $model): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $items = $model::withTrashed()->where($uniqueNumber, $uniqueNumberValue)->get();

        // Create a temporary zip file
        $zipFile = tempnam(sys_get_temp_dir(), 'attachments') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE);

        // Add all media files to the zip
        foreach ($items as $item) {
            $mediaItems = $item->getMedia('attachments');
            foreach ($mediaItems as $mediaItem) {
                $zip->addFile($mediaItem->getPath(), $mediaItem->file_name);
            }
        }

        $zip->close();

        // Return a response to download the zip file
        return response()->download($zipFile, $uniqueNumberValue . '-attachments.zip')
            ->deleteFileAfterSend(true);
    }


    public function getCurrentApprover($movementRequest)
    {
        $approver = $movementRequest->load('transferApproval')->transferApproval->where('status', 'For Approval')->first()->approver->user ?? null;
        if (!$approver) {
            $isFaApprove = $movementRequest->is_fa_approved;
            if (!$isFaApprove) {
                return [
                    'firstname' => 'For Approval of FA',
                    'lastname' => '',
                ];
            } else {
                return [
                    'firstname' => 'Transfer Approved',
                    'lastname' => '',
                ];
            }
        }
        return $approver;
    }

    public function getProcessCount($movementRequest)
    {
        if ($movementRequest->status == 'Cancelled') {
            return -1;
        }
        if ($movementRequest->status == 'Returned') {
            return 1;
        }
        $approver = $movementRequest->load('transferApproval')->transferApproval->where('status', 'For Approval')->first()->layer ?? null;
        if (!$approver) {
            $isFaApprove = $movementRequest->is_fa_approved;
            $allLayers = $movementRequest->load('transferApproval')->transferApproval->pluck('layer');
            if (!$isFaApprove) {
                return $allLayers->max() + 1;
            } else {
                return $allLayers->max() + 2;
            }
        }
        return $approver;

    }

    public function setSteps($movementRequest)
    {
        $approvers = $movementRequest->transferApproval;
        $approvers = $approvers->sortBy('layer');

        $steps = [];
        foreach ($approvers as $approver) {
            $steps[] = $approver->approver->user->firstname . ' ' . $approver->approver->user->lastname;
        }
        $steps[] = 'For Approval of FA';
        return $steps;
    }

    public function assetMovementLogger($movementRequest, $action, $modelInstance)
    {
        $user = auth('sanctum')->user();
        activity()
            ->causedBy($user)
            ->performedOn($modelInstance)
            ->withProperties([
                'action' => $action,
                'transfer_number' => $movementRequest->transfer_number,
                'remarks' => $movementRequest->remarks ?? null,
                'vladimir_tag_number' => $movementRequest->fixedAsset->vladimir_tag_number,
                'description' => $movementRequest->description,
            ])
            ->inLog(ucwords(strtolower($action)))
            ->tap(function ($activity) use ($movementRequest) {
                $activity->subject_id = $movementRequest->transfer_number;
            })
            ->log($action . ' Request');

    }

    private function transformSingleFixedAssetShowData($fixed_asset): array
    {
//        $fixed_asset->additional_cost_count = $fixed_asset->additionalCost ? $fixed_asset->additionalCost->count() : 0;
        return [
//            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->first_name ?? '-',
                'last_name' => $fixed_asset->requestor->last_name ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'po_number' => $fixed_asset->po_number ?? '-',
            'rr_number' => $fixed_asset->rr_number ?? '-',
            'warehouse_number' => [
                'id' => $fixed_asset->warehouseNumber->id ?? '-',
                'warehouse_number' => $fixed_asset->warehouseNumber->warehouse_number ?? '-',
            ],
            'from_request' => $fixed_asset->from_request ?? '-',
            'can_release' => $fixed_asset->can_release ?? '-',
            'capex' => [
                'id' => $fixed_asset->capex->id ?? '-',
                'capex' => $fixed_asset->capex->capex ?? '-',
                'project_name' => $fixed_asset->capex->project_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $fixed_asset->subCapex->id ?? '-',
                'sub_capex' => $fixed_asset->subCapex->sub_capex ?? '-',
                'sub_project' => $fixed_asset->subCapex->sub_project ?? '-',
            ],
            'capex_number' => $fixed_asset->capex_number ?? '-',
            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
            'tag_number' => $fixed_asset->tag_number ?? '-',
            'tag_number_old' => $fixed_asset->tag_number_old ?? '-',
            'asset_description' => $fixed_asset->asset_description ?? '-',
            'type_of_request' => [
                'id' => $fixed_asset->typeOfRequest->id ?? '-',
                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $fixed_asset->asset_specification ?? '-',
            'accountability' => $fixed_asset->accountability ?? '-',
            'accountable' => $fixed_asset->accountable ?? '-',
            'cellphone_number' => $fixed_asset->cellphone_number ?? '-',
            'brand' => $fixed_asset->brand ?? '-',
            'supplier' => [
                'id' => $fixed_asset->supplier->id ?? '-',
                'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
                'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
            ],
            'division' => [
                'id' => $fixed_asset->department->division->id ?? '-',
                'division_name' => $fixed_asset->department->division->division_name ?? '-',
            ],
            'major_category' => [
                'id' => $fixed_asset->majorCategory->id ?? '-',
                'major_category_name' => $fixed_asset->majorCategory->major_category_name ?? '-',
            ],
            'minor_category' => [
                'id' => $fixed_asset->minorCategory->id ?? '-',
                'minor_category_name' => $fixed_asset->minorCategory->minor_category_name ?? '-',
            ],
            'unit_of_measure' => [
                'id' => $fixed_asset->uom->id ?? '-',
                'uom_code' => $fixed_asset->uom->uom_code ?? '-',
                'uom_name' => $fixed_asset->uom->uom_name ?? '-',
            ],
            'est_useful_life' => $fixed_asset->majorCategory->est_useful_life ?? '-',
            'voucher' => $fixed_asset->voucher ?? '-',
            'voucher_date' => $fixed_asset->voucher_date ?? '-',
            'receipt' => $fixed_asset->receipt ?? '-',
            'quantity' => $fixed_asset->quantity ?? '-',
            'depreciation_method' => $fixed_asset->depreciation_method ?? '-',
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date ?? '-',
            'acquisition_cost' => $fixed_asset->acquisition_cost ?? '-',
            'scrap_value' => $fixed_asset->formula->scrap_value ?? '-',
            'depreciable_basis' => $fixed_asset->formula->depreciable_basis ?? '-',
            'accumulated_cost' => $fixed_asset->formula->accumulated_cost ?? '-',
            'asset_status' => [
                'id' => $fixed_asset->assetStatus->id ?? '-',
                'asset_status_name' => $fixed_asset->assetStatus->asset_status_name ?? '-',
            ],
            'cycle_count_status' => [
                'id' => $fixed_asset->cycleCountStatus->id ?? '-',
                'cycle_count_status_name' => $fixed_asset->cycleCountStatus->cycle_count_status_name ?? '-',
            ],
            'depreciation_status' => [
                'id' => $fixed_asset->depreciationStatus->id ?? '-',
                'depreciation_status_name' => $fixed_asset->depreciationStatus->depreciation_status_name ?? '-',
            ],
            'movement_status' => [
                'id' => $fixed_asset->movementStatus->id ?? '-',
                'movement_status_name' => $fixed_asset->movementStatus->movement_status_name ?? '-',
            ],
            'is_additional_cost' => $fixed_asset->is_additional_cost ?? '-',
            'is_old_asset' => $fixed_asset->is_old_asset ?? '-',
            'status' => $fixed_asset->is_active ?? '-',
            'care_of' => $fixed_asset->care_of ?? '-',
            'months_depreciated' => $fixed_asset->formula->months_depreciated ?? '-',
            'end_depreciation' => $fixed_asset->formula->end_depreciation ?? '-',
            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year ?? '-',
            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month ?? '-',
            'remaining_book_value' => $fixed_asset->formula->remaining_book_value ?? '-',
            'release_date' => $fixed_asset->formula->release_date ?? '-',
            'start_depreciation' => $fixed_asset->formula->start_depreciation ?? '-',
            'company' => [
                'id' => $fixed_asset->department->company->id ?? '-',
                'company_code' => $fixed_asset->department->company->company_code ?? '-',
                'company_name' => $fixed_asset->department->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->department->businessUnit->id ?? '-',
                'business_unit_code' => $fixed_asset->department->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->department->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'charged_department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'charged_department_code' => $fixed_asset->department->department_code ?? '-',
                'charged_department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->location->id ?? '-',
                'location_code' => $fixed_asset->location->location_code ?? '-',
                'location_name' => $fixed_asset->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $fixed_asset->accountTitle->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->account_title_name ?? '-',
            ],
            'remarks' => $fixed_asset->remarks,
            'print_count' => $fixed_asset->print_count,
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $fixed_asset->last_printed,
            'tagging' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
//            'additional_cost' => isset($fixed_asset->additionalCost) ? $fixed_asset->additionalCost->map(function ($additional_cost) {
//                return [
//                    'id' => $additional_cost->id ?? '-',
//                    'requestor' => [
//                        'id' => $additional_cost->requestor->id ?? '-',
//                        'username' => $additional_cost->requestor->username ?? '-',
//                        'first_name' => $additional_cost->requestor->first_name ?? '-',
//                        'last_name' => $additional_cost->requestor->last_name ?? '-',
//                        'employee_id' => $additional_cost->requestor->employee_id ?? '-',
//                    ],
//                    'pr_number' => $additional_cost->pr_number ?? '-',
//                    'po_number' => $additional_cost->po_number ?? '-',
//                    'rr_number' => $additional_cost->rr_number ?? '-',
//                    'warehouse_number' => [
//                        'id' => $additional_cost->warehouseNumber->id ?? '-',
//                        'warehouse_number' => $additional_cost->warehouseNumber->warehouse_number ?? '-',
//                    ],
//                    'from_request' => $additional_cost->from_request ?? '-',
//                    'can_release' => $additional_cost->can_release ?? '-',
//                    'add_cost_sequence' => $additional_cost->add_cost_sequence ?? '-',
//                    'asset_description' => $additional_cost->asset_description ?? '-',
//                    'type_of_request' => [
//                        'id' => $additional_cost->typeOfRequest->id ?? '-',
//                        'type_of_request_name' => $additional_cost->typeOfRequest->type_of_request_name ?? '-',
//                    ],
//                    'asset_specification' => $additional_cost->asset_specification ?? '-',
//                    'accountability' => $additional_cost->accountability ?? '-',
//                    'accountable' => $additional_cost->accountable ?? '-',
//                    'cellphone_number' => $additional_cost->cellphone_number ?? '-',
//                    'brand' => $additional_cost->brand ?? '-',
//                    'supplier' => [
//                        'id' => $fixed_asset->supplier->id ?? '-',
//                        'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
//                        'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
//                    ],
//                    'division' => [
//                        'id' => $additional_cost->department->division->id ?? '-',
//                        'division_name' => $additional_cost->department->division->division_name ?? '-',
//                    ],
//                    'major_category' => [
//                        'id' => $additional_cost->majorCategory->id ?? '-',
//                        'major_category_name' => $additional_cost->majorCategory->major_category_name ?? '-',
//                    ],
//                    'minor_category' => [
//                        'id' => $additional_cost->minorCategory->id ?? '-',
//                        'minor_category_name' => $additional_cost->minorCategory->minor_category_name ?? '-',
//                    ],
//                    'unit_of_measure' => [
//                        'id' => $additional_cost->uom->id ?? '-',
//                        'uom_code' => $additional_cost->uom->uom_code ?? '-',
//                        'uom_name' => $additional_cost->uom->uom_name ?? '-',
//                    ],
//                    'est_useful_life' => $additional_cost->majorCategory->est_useful_life ?? '-',
//                    'voucher' => $additional_cost->voucher ?? '-',
//                    'voucher_date' => $additional_cost->voucher_date ?? '-',
//                    'receipt' => $additional_cost->receipt ?? '-',
//                    'quantity' => $additional_cost->quantity ?? '-',
//                    'depreciation_method' => $additional_cost->depreciation_method ?? '-',
//                    //                    'salvage_value' => $additional_cost->salvage_value,
//                    'acquisition_date' => $additional_cost->acquisition_date ?? '-',
//                    'acquisition_cost' => $additional_cost->acquisition_cost ?? '-',
//                    'scrap_value' => $additional_cost->formula->scrap_value ?? '-',
//                    'depreciable_basis' => $additional_cost->formula->depreciable_basis ?? '-',
//                    'accumulated_cost' => $additional_cost->formula->accumulated_cost ?? '-',
//                    'asset_status' => [
//                        'id' => $additional_cost->assetStatus->id ?? '-',
//                        'asset_status_name' => $additional_cost->assetStatus->asset_status_name ?? '-',
//                    ],
//                    'cycle_count_status' => [
//                        'id' => $additional_cost->cycleCountStatus->id ?? '-',
//                        'cycle_count_status_name' => $additional_cost->cycleCountStatus->cycle_count_status_name ?? '-',
//                    ],
//                    'depreciation_status' => [
//                        'id' => $additional_cost->depreciationStatus->id ?? '-',
//                        'depreciation_status_name' => $additional_cost->depreciationStatus->depreciation_status_name ?? '-',
//                    ],
//                    'movement_status' => [
//                        'id' => $additional_cost->movementStatus->id ?? '-',
//                        'movement_status_name' => $additional_cost->movementStatus->movement_status_name ?? '-',
//                    ],
//                    'is_additional_cost' => $additional_cost->is_additional_cost ?? '-',
//                    'status' => $additional_cost->is_active ?? '-',
//                    'care_of' => $additional_cost->care_of ?? '-',
//                    'months_depreciated' => $additional_cost->formula->months_depreciated ?? '-',
//                    'end_depreciation' => $additional_cost->formula->end_depreciation ?? '-',
//                    'depreciation_per_year' => $additional_cost->formula->depreciation_per_year ?? '-',
//                    'depreciation_per_month' => $additional_cost->formula->depreciation_per_month ?? '-',
//                    'remaining_book_value' => $additional_cost->formula->remaining_book_value ?? '-',
//                    'release_date' => $additional_cost->formula->release_date ?? '-',
//                    'start_depreciation' => $additional_cost->formula->start_depreciation ?? '-',
//                    'company' => [
//                        'id' => $additional_cost->department->company->id ?? '-',
//                        'company_code' => $additional_cost->department->company->company_code ?? '-',
//                        'company_name' => $additional_cost->department->company->company_name ?? '-',
//                    ],
//                    'business_unit' => [
//                        'id' => $fixed_asset->department->businessUnit->id ?? '-',
//                        'business_unit_code' => $fixed_asset->department->businessUnit->business_unit_code ?? '-',
//                        'business_unit_name' => $fixed_asset->department->businessUnit->business_unit_name ?? '-',
//                    ],
//                    'department' => [
//                        'id' => $additional_cost->department->id ?? '-',
//                        'department_code' => $additional_cost->department->department_code ?? '-',
//                        'department_name' => $additional_cost->department->department_name ?? '-',
//                    ],
//                    'charged_department' => [
//                        'id' => $additional_cost->department->id ?? '-',
//                        'charged_department_code' => $additional_cost->department->department_code ?? '-',
//                        'charged_department_name' => $additional_cost->department->department_name ?? '-',
//                    ],
//                    'location' => [
//                        'id' => $additional_cost->location->id ?? '-',
//                        'location_code' => $additional_cost->location->location_code ?? '-',
//                        'location_name' => $additional_cost->location->location_name ?? '-',
//                    ],
//                    'account_title' => [
//                        'id' => $additional_cost->accountTitle->id ?? '-',
//                        'account_title_code' => $additional_cost->accountTitle->account_title_code ?? '-',
//                        'account_title_name' => $additional_cost->accountTitle->account_title_name ?? '-',
//                    ],
//                    'remarks' => $additional_cost->remarks ?? '-',
//                ];
//            }) : [],
        ];
    }


    //FOR TRANSFER UPDATE
    private function getTransferApproval($subunit_id)
    {
        return AssetTransferApprover::where('subunit_id', $subunit_id)
            ->orderBy('layer', 'asc')
            ->get();
    }

    private function getTransferRequest($transferNumber, $fixed_asset_id)
    {
        return AssetTransferRequest::withTrashed()->where('transfer_number', $transferNumber)
            ->where('fixed_asset_id', $fixed_asset_id)
            ->first();
    }

    public function createTransferRequest($tagNumber, $transferNumber, $request, $createdBy, $transferApproval)
    {
        list($isRequesterApprover, $isLastApprover, $requesterLayer) = $this->checkIfRequesterIsApprover($createdBy, $transferApproval);
        AssetTransferRequest::create([
            'created_by_id' => $createdBy,
            'status' => $isLastApprover
                ? 'Approved'
                : ($isRequesterApprover
                    ? 'For Approval of Approver ' . ($requesterLayer + 1)
                    : 'For Approval of Approver 1'),
            'fixed_asset_id' => $tagNumber['fixed_asset_id'],
            'transfer_number' => $transferNumber,
            'accountability' => $request->accountability,
            'accountable' => $request->accountable,
            'company_id' => $request->company_id,
            'business_unit_id' => $request->business_unit_id,
            'department_id' => $request->department_id,
            'unit_id' => $request->unit_id,
            'subunit_id' => $request->subunit_id,
            'location_id' => $request->location_id,
            'account_id' => $request->account_id,
            'remarks' => $request->remarks,
            'description' => $request->description,
        ]);
    }

    private function updateTransferRequest($transferRequest, $tagNumber, $request)
    {
        // Store the original state of the model
        $originalTransferRequest = clone $transferRequest;

        $transferRequest->update([
            'fixed_asset_id' => $tagNumber['fixed_asset_id'],
            'accountability' => $request->accountability,
            'accountable' => $request->accountable,
            'company_id' => $request->company_id,
            'business_unit_id' => $request->business_unit_id,
            'department_id' => $request->department_id,
            'unit_id' => $request->unit_id,
            'subunit_id' => $request->subunit_id,
            'location_id' => $request->location_id,
            'account_id' => $request->account_id,
            'remarks' => $request->remarks,
            'description' => $request->description,
        ]);

        // Check if any attributes have been changed
        if ($originalTransferRequest->isDirty()) {
            Cache::put('isDataUpdated', true, 60);
        }
    }

    private function deleteNonExistingTransfers($transferNumber, $tagNumbers)
    {
        AssetTransferRequest::where('transfer_number', $transferNumber)
            ->whereNotIn('fixed_asset_id', $tagNumbers)
            ->delete();
    }

}
