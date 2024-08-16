<?php

namespace App\Traits;

use App\Models\AdditionalCost;
use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Models\AssetApproval;
use App\Models\FixedAsset;
use App\Models\MinorCategory;
use App\Models\RoleManagement;
use App\Models\User;
use App\Traits\AddingPoHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\DepartmentUnitApprovers;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Pagination\LengthAwarePaginator;


trait AssetRequestHandler
{

    use AddingPoHandler;

    public function approverViewing($transactionNumber)
    {
        $transactionNumber = AssetRequest::where('transaction_number', $transactionNumber)->get();
        if ($transactionNumber->isEmpty()) {
            return [];
        }
        //get the quantity of the transaction number and sum it
        $quantity = $transactionNumber->sum('quantity');

        foreach ($transactionNumber as $transactionNumbers) {
            return [
                'id' => $transactionNumbers->id ?? null,
                'transaction_number' => $transactionNumbers->transaction_number,
                'number_of_item' => $quantity,
//                'status' => strpos($transactionNumbers->status, 'For Approval') === 0 ? 'For Approval' : ($transactionNumbers->is_fa_approved ? 'Approved' : 'For Approval'),
                'requester' => [
                    'id' => $transactionNumbers->requestor->id ?? '-',
                    'username' => $transactionNumbers->requestor->username ?? '-',
                    'employee_id' => $transactionNumbers->requestor->employee_id ?? '-',
                    'firstname' => $transactionNumbers->requestor->firstname ?? '-',
                    'lastname' => $transactionNumbers->requestor->lastname ?? '-',
                ],
                'asset_request' => [
//                    'id' => $transactionNumbers->transaction_number ?? '-',
                    'transaction_number' => $transactionNumbers->transaction_number ?? '-',
                    'date_requested' => $transactionNumbers->created_at ?? '-',
                    'status' => $transactionNumbers->status ?? '-',
                    'additional_info' => $transactionNumbers->additional_info ?? '-',
                    'acquisition_details' => $transactionNumbers->acquisition_details ?? '-',
                ],
            ];
        }

    }

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
            ->first()->layer ?? 1;
        if ($singleResult) {
            $query = AssetRequest::where($field, $referenceNumber)
                ->where('is_fa_approved', false)
                ->whereIn('status', [
                    'For Approval of Approver ' . $approverCount,
                    'Returned',
                    'Approved',
                    'Returned From Ymir'
                ]);
//            ->orWhere('filter', 'Waiting to send for PO');
            return $query->first();
        } else {
            $query = AssetRequest::where($field, $transactionNumber)
                ->where('is_fa_approved', false)
                ->whereIn('status', [
                    'For Approval of Approver ' . $approverCount,
                    'Approved',
                    'Returned',
                    'Returned From Ymir'
                ]);
            return $query->get();
        }
    }


    public function updateAssetRequest($assetRequest, $request, $save = true)
    {
        $accountTitleID = MinorCategory::with('accountTitle')->where('id', $request->minor_category_id)->first()->accountTitle->id;
        // Make changes to the $assetRequest object but don't save them
        $assetRequest->fill([
            'type_of_request_id' => $request->type_of_request_id,
            'attachment_type' => $request->attachment_type,
            'capex_number' => $request->capex_number,
            'accountability' => $request->accountability,
            'accountable' => $request->accountable ?? null,
            'asset_description' => $request->asset_description,
            'asset_specification' => $request->asset_specification ?? null,
            'cellphone_number' => $request->cellphone_number ?? null,
            'brand' => $request->brand ?? null,
            'quantity' => $request->quantity,
            'acquisition_details' => $request->acquisition_details ?? null,
            'additional_info' => $request->additional_info ?? null,
            'date_needed' => $request->date_needed ?? null,
            'fixed_asset_id' => $request->fixed_asset_id ?? null,
//            'account_title_id' => $request->account_title_id ?? null,
            'major_category_id' => $request->major_category_id,
            'minor_category_id' => $request->minor_category_id,
            'company_id' => $request->company_id,
            'business_unit_id' => $request->business_unit_id,
            'department_id' => $request->department_id,
            'unit_id' => $request->unit_id,
            'subunit_id' => $request->subunit_id,
            'location_id' => $request->location_id,
            'account_title_id' => $accountTitleID,
            'uom_id' => $request->uom_id ?? null,
            'receiving_warehouse_id' => $request->receiving_warehouse_id,
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
                'business_unit_id' => $request->business_unit_id,
                'department_id' => $request->department_id,
                'unit_id' => $request->unit_id,
                'subunit_id' => $request->subunit_id,
                'location_id' => $request->location_id,
                'acquisition_details' => $request->acquisition_details ?? null,
                'fixed_asset_id' => $request->fixed_asset_id ?? null,
//                'account_title_id' => $request->account_title_id ?? null,
                'receiving_warehouse_id' => $request->receiving_warehouse_id,
//                'uom_id' => $request->uom_id ?? null,
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
        //TODO: Make this last for only 20 mins if there is bug
        if ($totalAfterCount !== $totalBeforeCount) {
            Cache::put('isFileDataUpdated', true, now()->addMinutes(20));
        } else {
            Cache::put('isFileDataUpdated', false, now()->addMinutes(20));
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

    public function transformIndexAssetRequest($assetRequest)
    {
        $deletedQuantity = AssetRequest::onlyTrashed()->where('transaction_number', $assetRequest->transaction_number)->sum('quantity');
        return [
            'is_newly_sync' => $assetRequest->newly_sync,
            'id' => $assetRequest->transaction_number,
            'transaction_number' => $assetRequest->transaction_number,
            'item_count' => $assetRequest->quantity + $deletedQuantity ?? 0,
            'can_edit' => $assetRequest->is_fa_approved ? 0 : 1,
            'can_resubmit' => $assetRequest->is_fa_approved ? 0 : 1,
            'cancel_count' => $deletedQuantity ?? 0,
            'ordered' => $assetRequest->quantity + $deletedQuantity ?? '-',
            'delivered' => $assetRequest->quantity_delivered ?? '-',
            'remaining' => $assetRequest->quantity - $assetRequest->quantity_delivered ?? '-',
            'cancelled' => (int)$assetRequest->cancelled,
            'date_requested' => $this->getDateRequested($assetRequest->transaction_number),
            'remarks' => $assetRequest->remarks ?? '',
            'status' => $this->getStatus($assetRequest),
            'pr_number' => $assetRequest->pr_number ?? '-',
            'po_number' => $assetRequest->po_number ?? '-',
            'rr_number' => $assetRequest->rr_number ?? '-',
            'is_addcost' => $assetRequest->is_addcost ?? 0,
//            'dated_delivered' => $assetRequest->getLastestDeliveryDate() ?? '-',
//            'fixed_asset' => [
//                'id' => $ar->fixedAsset->id ?? '-',
//                'vladimir_tag_number' => $ar->fixedAsset->vladimir_tag_number ?? '-',
//            ],
            'acquisition_details' => $assetRequest->acquisition_details ?? '-',
            'warehouse' => [
                'id' => $assetRequest->receivingWarehouse->id ?? '-',
                'warehouse_name' => $assetRequest->receivingWarehouse->warehouse_name ?? '-',
            ],
            'deleted_at' => $assetRequest->deleted_at,
//            'created_at' => $this->getDateRequested($assetRequest->transaction_number),
            'created_at' => $assetRequest->getLatestDeliveryDate(),
            'approver_count' => $assetRequest->assetApproval->count(),
            'process_count' => $this->getProcessCount($assetRequest) ?? 0,
            'current_approver' => $this->getCurrentApprover($assetRequest),
            'requestor' => $this->getRequestor($assetRequest),
            'history' => Activity::whereSubjectType(AssetRequest::class)
                ->whereSubjectId($assetRequest->transaction_number)
                ->get()
                ->map(function ($activityLog) {
                    return [
                        'id' => $activityLog->id,
                        'action' => $activityLog->log_name,
                        'causer' => $activityLog->causer,
                        'created_at' => $activityLog->created_at,
                        'remarks' => $activityLog->properties['remarks'] ?? null,
                        'received_by' => $activityLog->properties['received_by'] ?? null,
                        'asset_description' => $activityLog->properties['description'] ?? null,
                        'vladimir_tag_number' => $activityLog->properties['vladimir_tag'] ?? null,
                    ];
                }),

//                $this->getHistory($assetRequest),
            'steps' => $this->getSteps($assetRequest),
        ];
    }

    private function getStatus($assetRequest)
    {
        $allItems = AssetRequest::where('transaction_number', $assetRequest->transaction_number)->get();

        $allDeleted = $allItems->every(function ($item) {
            return $item->deleted_at !== null;
        });

        if ($allDeleted) {
            return 'Cancelled';
        }
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
                'received_by' => $activityLog->properties['received_by'] ?? null,
                'asset_description' => $activityLog->properties['description'] ?? null,
                'vladimir_tag_number' => $activityLog->properties['vladimir_tag'] ?? null,
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
            if ($assetRequest->is_fa_approved == false) {
                return [
                    'firstname' => 'For Approval of FA',
                    'lastname' => '',
                ];
            }
            if ($assetRequest->is_fa_approved == true) {
                return [
                    'firstname' => 'Sent to ymir for PO',
                    'lastname' => '',
                ];
            }

            if ($assetRequest->is_addcost != 1 && $assetRequest->filter == "Received") {
                return [
                    'firstname' => 'Asset Tagging',
                    'lastname' => '',
                ];
            }
            if (($assetRequest->filter == "Ready to Pickup") || ($assetRequest->is_addcost == 1 && $assetRequest->filter == "Ready to Pickup")) {
                return [
                    'firstname' => 'Ready to Pickup',
                    'lastname' => '',
                ];
            }
            if (($assetRequest->is_claimed == 1 && $assetRequest->filter == "Claimed") || ($assetRequest->is_claimed == 1 && $assetRequest->is_addcost == 1 && $assetRequest->filter == "Claimed")) {
                return [
                    'firstname' => 'Claimed',
                    'lastname' => '',
                ];
            }
            if ($assetRequest->deleted_at != null) {
                return [
                    'firstname' => 'Deleted',
                    'lastname' => '',
                ];
            }
        } else {
            return [
                'firstname' => $this->deletedItemCheck($assetRequest),
                'lastname' => '',
            ];
        }
        return 'Something went wrong';
    }

    private function getAfterApprovedStatus($assetRequest): string
    {
        $approvers = $assetRequest->status == 'Approved';
//        $faApproved = $assetRequest->is_fa_approved == true;
        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number);
        if ($approvers) {

            if (!$assetRequest->is_fa_approved) {
                return 'For Approval of FA';
            }
            if ($assetRequest->is_fa_approved) {
                return 'Sent to ymir for PO';
            }
//            if ($assetRequest->pr_number == null) {
//                return 'Inputting of PR No.';
//            }
            //check if null po number
//            if (($assetRequest->po_number == null && $assetRequest->pr_number != null) ||
//                ($remaining !== 0 && $assetRequest->po_number != null && $assetRequest->pr_number != null)
//            ) {
//                return 'Inputting of PO No. and RR No.';
//            }
            //$assetRequest->is_addcost != 1 && $assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->print_count != $assetRequest->quantity
            if ($assetRequest->is_addcost != 1 && $assetRequest->filter == "Received") {
                return 'Asset Tagging';
            }

            //($assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->print_count >= $assetRequest->quantity && $assetRequest->is_claimed == 0) ||
            //                ($assetRequest->po_number != null && $assetRequest->pr_number != null && $assetRequest->is_claimed == 0 && $assetRequest->is_addcost == 1)
            if (($assetRequest->filter == "Ready to Pickup") || ($assetRequest->is_addcost == 1 && $assetRequest->filter == "Ready to Pickup")) {
                return 'Ready to Pickup';
            }
            if (($assetRequest->is_claimed == 1 && $assetRequest->filter == "Claimed") || ($assetRequest->is_claimed == 1 && $assetRequest->is_addcost == 1 && $assetRequest->filter == "Claimed")) {
                return 'Claimed';
            }
        }
//        $this->deletedItemCheck($assetRequest);

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
        $steps[] = 'For Approval of FA';
        $steps[] = 'Sent to ymir for PO.';
//        $steps[] = 'Inputting of PO No. and RR No.';
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
        $returnStatus = $assetRequest->assetApproval->whereIn('status', ['Returned', 'Returned From Ymir'])->count();
        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number);

        if ($statusForApprovalCount > 0) {
            $lastLayer = $statusForApproval->first()->layer;
        } elseif ($returnStatus > 0) {
            $lastLayer = 1;
        } else {
            $lastLayer = $highestLayerNumber ?? 0;
//            dd($assetRequest->pr_number);
            if ($assetRequest->is_fa_approved == false) $lastLayer++;
            if ($assetRequest->is_fa_approved == true) $lastLayer += 2;
            if ($assetRequest->is_addcost != 1 && $assetRequest->filter == "Received") $lastLayer += 3;
            if (($assetRequest->filter == "Ready to Pickup") || ($assetRequest->is_addcost == 1 && $assetRequest->filter == "Ready to Pickup")) $lastLayer += 4;
            if (($assetRequest->is_claimed == 1 && $assetRequest->filter == "Claimed") || ($assetRequest->is_claimed == 1 && $assetRequest->is_addcost == 1 && $assetRequest->filter == "Claimed")) $lastLayer += 6;
            if ($this->deletedItemCheck($assetRequest) != null) $lastLayer = -1;
//            if($assetRequest->filter == "Returned From Ymir") $lastLayer = 1;
        }
        return $lastLayer;
    }

    public function deletedItemCheck($assetRequest)
    {
        $allItems = AssetRequest::withTrashed()->where('transaction_number', $assetRequest->transaction_number)->get();

        $allDeleted = $allItems->every(function ($item) {
            return $item->deleted_at !== null;
        });

        if ($allDeleted) {
            return 'Cancelled';
        }
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
            'warehouse' => [
                'id' => $assetRequest->receivingWarehouse->id ?? '-',
                'warehouse_name' => $assetRequest->receivingWarehouse->warehouse_name ?? '-',
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

    private function activityForRequestorDelete($assetRequest)
    {
        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        activity()
            ->causedBy($user)
            ->performedOn($assetRequests)
            ->withProperties($this->composeLogPropertiesRequest($assetRequest))
            ->inLog("Cancelled")
            ->tap(function ($activity) use ($user, $assetRequest) {
                // If $aRequest is not a collection, make it a collection
                if (!($assetRequest instanceof \Illuminate\Support\Collection)) {
                    $assetRequest = collect([$assetRequest]);
                }

                $firstAssetRequest = $assetRequest->first();
                if ($firstAssetRequest) {
                    $activity->subject_id = $firstAssetRequest->transaction_number;
                }
            })
            ->log('Cancelled');

    }

    private function composeLogPropertiesRequest($assetRequest): array
    {
        // If $assetRequest is not a collection, make it a collection
        if (!($assetRequest instanceof \Illuminate\Support\Collection)) {
            $assetRequest = collect([$assetRequest]);
        }

        // Map over the collection to get all descriptions
        $descriptions = $assetRequest->map(function ($item) {
            return $item->asset_description;
        });

        // Join all descriptions into a single string
        $descriptionString = $descriptions->join(', ');

        return [
            'description' => $descriptionString,
            'remarks' => null,
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
        $this->activityForRequestorDelete($assetRequest);
//        $this->removeMediaAttachments($assetRequest);
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $assetRequest->update([
            'deleter_id' => auth('sanctum')->user()->id,
            'filter' => null,
        ]);
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
        $this->activityForRequestorDelete($assetRequest);
        // $assetRequest->activityLog()->delete();
        foreach ($assetRequest as $ar) {
//            $this->removeMediaAttachments($ar);
//            $ar->activityLog()->delete();
            $ar->update([
                'deleter_id' => auth('sanctum')->user()->id,
                'filter' => null,
            ]);
            $ar->delete();
        }
        return $this->responseSuccess('Asset Request deleted Successfully');
    }

    private function isUserAnApprover($transactionNumber)
    {
        $user = auth('sanctum')->user()->id;
        $approversId = Approvers::where('approver_id', $user)->first()->id ?? null;
        if ($approversId == null) {
            return null;
        }
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
        $toNull = AssetApproval::where('transaction_number', $transactionNumber)->get();
        foreach ($toNull as $tn) {
            $tn->update([
                'status' => null
            ]);
        }
//        return AssetApproval::where('transaction_number', $transactionNumber)->delete();
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
//        return $assetRequest->transaction_number . '-' . $requesterId. '-' . $items[0]->subunit_id;
        $departmentUnitApprovers = DepartmentUnitApprovers::with('approver')->where('subunit_id', $items[0]->subunit_id)
            ->orderBy('layer')
            ->get();

        $layerIds = $departmentUnitApprovers->map(function ($approverObject) {
            return $approverObject->approver->approver_id;
        })->toArray();
        $isRequesterApprover = in_array($requesterId, $layerIds);
        $requesterLayer = array_search($requesterId, $layerIds) + 1;
//        return $layerIds ?? 'none';
//
//        return 'none';

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

    //ITEM DETAILS
    public function getFAItemDetails($referenceNumber)
    {
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
        return $fixedAsset;
    }

    public function getACItemDetails($referenceNumber)
    {
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
        return $additionalCost;
    }

    public function getARItemDetails($referenceNumber)
    {
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
        return $assetRequest;
    }
}
