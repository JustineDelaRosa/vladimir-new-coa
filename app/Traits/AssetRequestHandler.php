<?php

namespace App\Traits;

use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Models\AssetApproval;
use App\Traits\AddingPoHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\DepartmentUnitApprovers;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Pagination\LengthAwarePaginator;


trait AssetRequestHandler
{

    use AddingPoHandler;

    public function getAssetRequest($field, $value, $singleResult = true)
    {
        $query = AssetRequest::where($field, $value)
            ->whereIn('status', ['For Approval of Approver 1', 'Returned']);

        return $singleResult ? $query->first() : $query->get();
    }

    public function getAssetRequestForApprover($field, $transactionNumber, $referenceNumber = null, $singleResult = true)
    {

        //TODO:: CHECK THIS
        $approverCount = AssetApproval::where('transaction_number', $transactionNumber)->whereIN('status', ['For Approval', 'Returned'])
            ->first()->layer ;
        if ($singleResult) {
            $query = AssetRequest::where($field, $referenceNumber)
                ->whereIn('status', [
                    'For Approval of Approver ' . $approverCount,
                    'Returned'
                ]);
            return $query->first();
        } else {
            $query = AssetRequest::where($field, $transactionNumber)
                ->whereIn('status', [
                    'For Approval of Approver ' . $approverCount,
                    'Approved',
                    'Returned'
                ]);
            return $query->get();
        }
    }

    public function updateAssetRequest($assetRequest, $request, $save = true)
    {
        // Make changes to the $assetRequest object but don't save them
        $assetRequest->fill([
            'type_of_request_id' => $request->type_of_request_id,
            'attachment_type' => $request->attachment_type,
            'accountability' => $request->accountability,
            'accountable' => $request->accountable ?? null,
            'asset_description' => $request->asset_description,
            'asset_specification' => $request->asset_specification ?? null,
            'cellphone_number' => $request->cellphone_number ?? null,
            'brand' => $request->brand ?? null,
            'quantity' => $request->quantity,
            'company_id' => $request->company_id,
            'department_id' => $request->department_id,
            'subunit_id' => $request->subunit_id,
            'location_id' => $request->location_id,
            'acquisition_details' => $request->acquisition_details ?? null,
            'date_needed' => $request->date_needed ?? null,
            'fixed_asset_id' => $request->fixed_asset_id ?? null,
        ]);

        $this->updateOtherRequestChargingDetails($assetRequest, $request, $save);
        if ($save) {
            $assetRequest->save();
        }
        return $assetRequest;
    }

    public function updateOtherRequestChargingDetails($assetRequest, $request, $save = true)
    {
        $allRequest = AssetRequest::where('transaction_number', $assetRequest->transaction_number)->where('id', '!=', $assetRequest->id)
            ->get();
        $ar = null;
        foreach ($allRequest as $ar) {
            $ar->update([
                'company_id' => $request->company_id,
                'department_id' => $request->department_id,
                'subunit_id' => $request->subunit_id,
                'location_id' => $request->location_id,
                'acquisition_details' => $request->acquisition_details ?? null,
                'fixed_asset_id' => $request->fixed_asset_id ?? null,
            ]);
        }
        return $ar;
    }

    public function handleMediaAttachments($assetRequest, $request)
    {
        $collections = [
            'letter_of_request',
            'quotation',
            'specification_form',
            'tool_of_trade',
            'other_attachments'
        ];

        //count the media attachments before the update
        // Initialize total counts
        $totalBeforeCount = 0;
        $totalAfterCount = 0;

        foreach ($collections as $collection) {
            // Get the count of media items in the collection before the update
            $beforeCount = $assetRequest->getMedia($collection)->count();
            $totalBeforeCount += $beforeCount;

            if ($request->$collection !== 'x') {
                if (isset($request->$collection)) {
                    $assetRequest->clearMediaCollection($collection);
                    $assetRequest->addMultipleMediaFromRequest([$collection], $collection)->each(function ($fileAdder) use ($collection) {
                        $fileAdder->toMediaCollection($collection);
                    });
                } else {
                    $assetRequest->clearMediaCollection($collection);
                }
            }

            // Get the count of media items in the collection after the update
            $afterCount = $assetRequest->getMedia($collection)->count();
            $totalAfterCount += $afterCount;

        }
        if ($totalAfterCount !== $totalBeforeCount) {
            Cache::put('isFileDataUpdated', true);
        }
    }

    private function removeMediaAttachments($assetRequest)
    {
        $collections = [
            'letter_of_request',
            'quotation',
            'specification_form',
            'tool_of_trade',
            'other_attachments'
        ];

        foreach ($collections as $collection) {
            $assetRequest->clearMediaCollection($collection);
        }
    }

    public function transformIndexAssetRequest($assetRequest): array
    {
        return [
            'id' => $assetRequest->transaction_number,
            'transaction_number' => $assetRequest->transaction_number,
            'item_count' => $assetRequest->quantity ?? 0,
            'date_requested' => $this->getDateRequested($assetRequest->transaction_number),
            'remarks' => $assetRequest->remarks ?? '',
            'status' => $this->getStatus($assetRequest),
            'pr_number' => $assetRequest->pr_number ?? '-',
            'po_number' => $assetRequest->po_number ?? '-',
            'rr_number' => $assetRequest->rr_number ?? '-',
            'is_addcost' => $assetRequest->is_addcost ?? 0,
            'fixed_asset' => [
                'id' => $ar->fixedAsset->id ?? '-',
                'vladimir_tag_number' => $ar->fixedAsset->vladimir_tag_number ?? '-',
            ],
            'acquisition_details' => $assetRequest->acquisition_details ?? '-',
            'created_at' => $this->getDateRequested($assetRequest->transaction_number),
            'approver_count' => $assetRequest->assetApproval->count(),
            'process_count' => $this->getProcessCount($assetRequest),
            'current_approver' => $this->getCurrentApprover($assetRequest),
            'requestor' => $this->getRequestor($assetRequest),
            'history' => $this->getHistory($assetRequest),
            'steps' => $this->getSteps($assetRequest),
        ];
    }

    private function getStatus($assetRequest)
    {
        return $assetRequest->status == 'Approved' ? $this->getAfterApprovedStatus($assetRequest) : $assetRequest->status;
    }

    private function getCurrentApprover($assetRequest)
    {
        return $assetRequest->assetApproval->filter(function ($approval) {
            return $approval->status == 'For Approval';
        })->map(function ($approval) {
            return [
                'id' => $approval->approver->user->id,
                'username' => $approval->approver->user->username,
                'employee_id' => $approval->approver->user->employee_id,
                'firstname' => $approval->approver->user->firstname,
                'lastname' => $approval->approver->user->lastname,
                'department' => $approval->approver->user->department->department_name ?? '-',
                'subunit' => $approval->approver->user->subUnit->sub_unit_name ?? '-',
                'layer' => $approval->layer ?? '',
            ];
        })->values()->first() ?? $this->getAfterApprovedStep($assetRequest);
    }

    private function getRequestor($assetRequest)
    {
        return [
            'id' => $assetRequest->requestor->id ?? '-',
            'username' => $assetRequest->requestor->username ?? '-',
            'employee_id' => $assetRequest->requestor->employee_id ?? '-',
            'firstname' => $assetRequest->requestor->firstname ?? '-',
            'lastname' => $assetRequest->requestor->lastname ?? '-',
            'department' => $assetRequest->requestor->department->department_name ?? '-',
            'subunit' => $assetRequest->requestor->subUnit->sub_unit_name ?? '-',
        ];
    }

    private function getHistory($assetRequest)
    {
        return $assetRequest->activityLog->map(function ($activityLog) {
            return [
                'id' => $activityLog->id,
                'action' => $activityLog->log_name,
                'causer' => $activityLog->causer,
                'created_at' => $activityLog->created_at,
                'remarks' => $activityLog->properties['remarks'] ?? null,
            ];
        });
    }

    private function getAfterApprovedStep($assetRequest)
    {
        //check if the status is approved
        $approvers = $assetRequest->status == 'Approved';
        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number);
        if ($approvers) {
            //check if null pr number
            if ($assetRequest->pr_number == null) {
                return [
                    'firstname' => 'Inputting of PR No.',
                    'lastname' => '',
                ];
            }
            //check if null po number
            if (($assetRequest->po_number == null && $assetRequest->pr_number != null) ||
                ($remaining !== 0 && $assetRequest->po_number != null && $assetRequest->pr_number != null)
            ) {
                return [
                    'firstname' => 'Inputting of PO No. and RR No.',
                    'lastname' => '',
                ];
            }

            if ($assetRequest->is_addcost != 1 && $assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->print_count != $assetRequest->quantity) {
                return [
                    'firstname' => 'Asset Tagging',
                    'lastname' => '',
                ];
            }
            if (($assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->print_count >= $assetRequest->quantity && $assetRequest->is_claimed == 0) ||
                ($assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->is_claimed == 0 && $assetRequest->is_addcost == 1)) {
                return [
                    'firstname' => 'Ready to Pickup',
                    'lastname' => '',
                ];
            }
            if ($assetRequest->is_claimed = 1) {
                return [
                    'firstname' => 'Claimed',
                    'lastname' => '',
                ];
            }

        }

        return 'Something went wrong';
    }

    private function getAfterApprovedStatus($assetRequest): string
    {
        $approvers = $assetRequest->status == 'Approved';
        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number);
        if ($approvers) {
            //check if null pr number
            if ($assetRequest->pr_number == null) {
                return 'Inputting of PR No.';
            }
            //check if null po number
            if (($assetRequest->po_number == null && $assetRequest->pr_number != null) ||
                ($remaining !== 0 && $assetRequest->po_number != null && $assetRequest->pr_number != null)
            ) {
                return 'Inputting of PO No. and RR No.';
            }

            if ($assetRequest->is_addcost != 1 && $assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->print_count != $assetRequest->quantity) {
                return 'Asset Tagging';
            }
            if (($assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->print_count >= $assetRequest->quantity && $assetRequest->is_claimed == 0) ||
                ($assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->is_claimed == 0 && $assetRequest->is_addcost == 1)) {
                return 'Ready to Pickup';
            }
            if (($assetRequest->is_claimed == 1 && $assetRequest->print_count = $assetRequest->quantity) || ($assetRequest->is_claimed == 1 && $assetRequest->is_addcost == 1)) {
                return 'Claimed';
            }
        }

        return 'Something went wrong';
    }


    private function getSteps($assetRequest): array
    {
        $approvers = $assetRequest->assetApproval;
        $approvers = $approvers->sortBy('layer');

        $steps = [];
        foreach ($approvers as $approver) {
            $steps[] = $approver->approver->user->firstname . ' ' . $approver->approver->user->lastname;
        }
        $steps[] = 'Inputting of PR No.';
        $steps[] = 'Inputting of PO No. and RR No.';
        $steps[] = 'Asset Tagging';
        $steps[] = 'Ready to Pickup';
        $steps[] = 'Claimed';

        return $steps;
    }

    private function getProcessCount($assetRequest)
    {
        // return $this->calculateRemainingQuantity($assetRequest->transaction_number);
        $statusForApproval = $assetRequest->assetApproval->where('status', 'For Approval');
        $highestLayerNumber = $assetRequest->assetApproval()->max('layer');
        $statusForApprovalCount = $statusForApproval->count();
        $returnStatus = $assetRequest->assetApproval->where('status', 'Returned')->count();
        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number);

        if ($statusForApprovalCount > 0) {
            $lastLayer = $statusForApproval->first()->layer;
        } elseif ($returnStatus > 0) {
            $lastLayer = 1;
        } else {
            $lastLayer = $highestLayerNumber ?? 0;
//            dd($assetRequest->pr_number);
            if ($assetRequest->pr_number === null) $lastLayer++;
            if (($assetRequest->po_number == null && $assetRequest->pr_number != null) ||
                ($remaining !== 0 && $assetRequest->po_number != null && $assetRequest->pr_number != null)
            ) $lastLayer += 2;
            if ($assetRequest->is_addcost != 1 && $assetRequest->po_number != null && $assetRequest->pr_number != null && $remaining == 0 && $assetRequest->print_count != $assetRequest->quantity) $lastLayer += 3;
            if (($assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->print_count >= $assetRequest->quantity && $assetRequest->is_claimed == 0) ||
                ($assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->is_claimed == 0 && $assetRequest->is_addcost == 1)) $lastLayer += 4;
            if (($assetRequest->is_claimed == 1 && $assetRequest->print_count = $assetRequest->quantity) || ($assetRequest->is_claimed == 1 && $assetRequest->is_addcost == 1)) $lastLayer += 6;
        }
        return $lastLayer;
    }

    private function getDateRequested($transactionNumber)
    {
        // Get the assetRequest associated with the transaction number
        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->first();

        // Return the created_at field
        return $assetRequest ? $assetRequest->created_at : null;
    }

    public function transformForSingleItemOnly($assetRequest): array
    {
        return [
            'id' => $assetRequest->id,
            'status' => $assetRequest->status,
            'transaction_number' => $assetRequest->transaction_number,
            'reference_number' => $assetRequest->reference_number,
            'pr_number' => $assetRequest->pr_number ?? '-',
            'po_number' => $assetRequest->po_number ?? '-',
            'rr_number' => $assetRequest->rr_number ?? '-',
            'is_addcost' => $assetRequest->is_addcost ?? 0,
            'attachment_type' => $assetRequest->attachment_type,
            'remarks' => $assetRequest->remarks ?? '',
            'additional_info' => $assetRequest->additional_info ?? '-',
            'acquisition_details' => $assetRequest->acquisition_details ?? '-',
            'accountability' => $assetRequest->accountability,
            'accountable' => $assetRequest->accountable ?? '-',
            'asset_description' => $assetRequest->asset_description,
            'fixed_asset' => [
                'id' => $ar->fixedAsset->id ?? '-',
                'vladimir_tag_number' => $ar->fixedAsset->vladimir_tag_number ?? '-',
            ],
            'asset_specification' => $assetRequest->asset_specification ?? '-',
            'cellphone_number' => $assetRequest->cellphone_number ?? '-',
            'brand' => $assetRequest->brand ?? '-',
            'quantity' => $assetRequest->quantity,
            'requestor' => [
                'id' => $assetRequest->requestor->id,
                'username' => $assetRequest->requestor->username,
                'employee_id' => $assetRequest->requestor->employee_id,
                'firstname' => $assetRequest->requestor->firstname,
                'lastname' => $assetRequest->requestor->lastname,
            ],
            'type_of_request' => [
                'id' => $assetRequest->typeOfRequest->id,
                'type_of_request_name' => $assetRequest->typeOfRequest->type_of_request_name,
            ],
            'company' => [
                'id' => $assetRequest->company->id,
                'company_name' => $assetRequest->company->company_name,
            ],
            'department' => [
                'id' => $assetRequest->department->id,
                'charged_department_name' => $assetRequest->department->department_name,
            ],
            'subunit' => [
                'id' => $assetRequest->subunit->id,
                'subunit_name' => $assetRequest->subunit->sub_unit_name,
            ],
            'location' => [
                'id' => $assetRequest->location->id,
                'location_name' => $assetRequest->location->location_name,
            ],
            'account_title' => [
                'id' => $assetRequest->accountTitle->id,
                'account_title_name' => $assetRequest->accountTitle->account_title_name
            ],
            'attachments' => [
                'letter_of_request' => $assetRequest->getMedia('letter_of_request')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'file_name' => $media->file_name,
                        'file_path' => $media->getPath(),
                        'file_url' => $media->getUrl(),
                    ];
                }),
                'quotation' => $assetRequest->getMedia('quotation')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'file_name' => $media->file_name,
                        'file_path' => $media->getPath(),
                        'file_url' => $media->getUrl(),
                    ];
                }),
                'specification_form' => $assetRequest->getMedia('specification_form')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'file_name' => $media->file_name,
                        'file_path' => $media->getPath(),
                        'file_url' => $media->getUrl(),
                    ];
                }),
                'tool_of_trade' => $assetRequest->getMedia('tool_of_trade')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'file_name' => $media->file_name,
                        'file_path' => $media->getPath(),
                        'file_url' => $media->getUrl(),
                    ];
                }),
                'other_attachments' => $assetRequest->getMedia('other_attachments')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'file_name' => $media->file_name,
                        'file_path' => $media->getPath(),
                        'file_url' => $media->getUrl(),
                    ];
                }),
            ]
        ];
    }

    public function deleteRequestItem($referenceNumber, $transactionNumber): JsonResponse
    {

        $approverId = $this->isUserAnApprover($transactionNumber);

        // return $this->responseUnprocessable($assetRequest);
        if (!$approverId) {
            $assetRequest = $this->getAssetRequest('reference_number', $referenceNumber);
            if (!$assetRequest) {
                return $this->responseUnprocessable('Unable to Delete Request Item.');
            }
        } else {
            $assetRequest = $this->getAssetRequestForApprover('reference_number', $transactionNumber, $referenceNumber);
        }

        // $assetRequest->transaction_number
        if ($this->requestCount($transactionNumber) == 1) {
            return $this->responseUnprocessable('You cannot delete the last item.');
        }
        $this->removeMediaAttachments($assetRequest);
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Perform the delete operation
        $assetRequest->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $cookie = cookie('is_changed', true);
        return $this->responseSuccess('Asset Request deleted Successfully')->withCookie($cookie);
    }

    public function deleteAssetRequest($transactionNumber)
    {
        // return $this->responseSuccess($this->isUserAnApprover($transactionNumber));
        $approverId = $this->isUserAnApprover($transactionNumber);

        // return $this->responseUnprocessable($assetRequest);
        if ($approverId == null) {
            $assetRequest = $this->getAssetRequest('transaction_number', $transactionNumber, false);
            if ($assetRequest->isEmpty()) {
                return $this->responseUnprocessable('Unable to Delete Asset Request.');
            }
        } else {
            $assetRequest = $this->getAssetRequestForApprover('transaction_number', $transactionNumber, null, false);
        }
//        return $assetRequest;
//
//        // return $this->deleteApprovals($assetRequest->transaction_number, 'Void') . 'asdfasdf';
        $this->deleteApprovals($transactionNumber);
        // $assetRequest->activityLog()->delete();
        foreach ($assetRequest as $ar) {
            $this->removeMediaAttachments($ar);
            $ar->activityLog()->delete();
            $ar->delete();
        }
        return $this->responseSuccess('Asset Request deleted Successfully');
    }

    private function isUserAnApprover($transactionNumber)
    {
        $user = auth('sanctum')->user()->id;
        $approversId = Approvers::where('approver_id', $user)->first()->id;
        $approverId = AssetApproval::where('transaction_number', $transactionNumber)
            ->where('status', 'approved')->where('approver_id', $approversId)->first();

        return $approverId;
    }

    public function requestCount($transactionNumber)
    {
        $requestCount = AssetRequest::where('transaction_number', $transactionNumber)->count();
        return $requestCount;
    }

    public function deleteApprovals($transactionNumber)
    {
        // $toVoid = AssetApproval::where('transaction_number', $transactionNumber)->get()
        return AssetApproval::where('transaction_number', $transactionNumber)->delete();
    }

    //THIS IS FOR STORE ASSET REQUEST
    /*   public function createAssetApprovals($departmentUnitApprovers, $isRequesterApprover, $requesterLayer, $assetRequest, $requesterId)
    {
        foreach ($departmentUnitApprovers as $departmentUnitApprover) {
            $approver_id = $departmentUnitApprover->approver_id;
            $layer = $departmentUnitApprover->layer;

            // initial status
            $status = null;

            // if the requester is the approver, decide on status
            if ($isRequesterApprover) {
                if ($layer == $requesterLayer || $layer < $requesterLayer) {
                    $status = "Approved";
                } elseif ($layer == $requesterLayer + 1) {
                    $status = "For Approval";
                }
            } elseif ($layer == 1) { // if the requester is not an approver, only the first layer should be "For Approval"
                $status = "For Approval";
            }

            AssetApproval::create([
                'transaction_number' => $assetRequest->transaction_number,
                'approver_id' => $approver_id,
                'requester_id' => $requesterId,
                'layer' => $layer,
                'status' => $status,
            ]);
        }
    }
     */

    //THIS IS FOR MOVING ASSET CONTAINER TO ASSET REQUEST
    public function createAssetApprovals($items, $requesterId, $assetRequest)
    {
        $departmentUnitApprovers = DepartmentUnitApprovers::with('approver')->where('subunit_id', $items[0]->subunit_id)
            ->orderBy('layer', 'asc')
            ->get();

        $layerIds = $departmentUnitApprovers->map(function ($approverObject) {
            return $approverObject->approver->approver_id;
        })->toArray();
        $isRequesterApprover = in_array($requesterId, $layerIds);
        $requesterLayer = array_search($requesterId, $layerIds) + 1;

        foreach ($departmentUnitApprovers as $departmentUnitApprover) {
            $approver_id = $departmentUnitApprover->approver_id;
            $layer = $departmentUnitApprover->layer;

            // initial status
            $status = null;

            // if the requester is the approver, decide on status
            if ($isRequesterApprover) {
                if ($layer == $requesterLayer || $layer < $requesterLayer) {
                    $status = "Approved";
                } elseif ($layer == $requesterLayer + 1) {
                    $status = "For Approval";
                }
            } elseif ($layer == 1) { // if the requester is not an approver, only the first layer should be "For Approval"
                $status = "For Approval";
            }

            AssetApproval::create([
                'transaction_number' => $assetRequest->transaction_number,
                'approver_id' => $approver_id,
                'requester_id' => $requesterId,
                'layer' => $layer,
                'status' => $status,
            ]);
        }
    }
}
