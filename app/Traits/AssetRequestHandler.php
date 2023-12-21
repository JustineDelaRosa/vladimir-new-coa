<?php

namespace App\Traits;

use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Models\AssetApproval;
use Illuminate\Http\JsonResponse;
use App\Models\DepartmentUnitApprovers;
use Illuminate\Pagination\LengthAwarePaginator;

trait AssetRequestHandler
{
    public function getAssetRequest($field, $value, $singleResult = true)
    {
        $query = AssetRequest::where($field, $value)
            ->whereIn('status', ['For Approval of Approver 1', 'Returned']);

        return $singleResult ? $query->first() : $query->get();
    }

    public function getAssetRequestForApprover($field, $transactionNumber, $referenceNumber = null, $singleResult = true)
    {
        $approverCount = AssetApproval::where('transaction_number', $transactionNumber)->where('status', 'For Approval')
            ->first()->layer;
        if ($singleResult == true) {
            $query = AssetRequest::where($field, $referenceNumber)
                ->whereIn('status', [
                    'For Approval of Approver ' . $approverCount,
                    'Returned'
                ]);
            return $query->first();
        } else {
            $query = AssetRequest::where($field, $transactionNumber)
                ->whereNotIn('status', [
                    'For Approval of Approver ' . $approverCount,
                    'Approved',
                    'Returned'
                ]);
            return $query->get();
        }
    }

    public function updateAssetRequest($assetRequest, $request)
    {
        return $assetRequest->update([
            'type_of_request_id' => $request->type_of_request_id,
            'attachment_type' => $request->attachment_type,
            'accountability' => $request->accountability,
            'accountable' => $request->accountable ?? null,
            'asset_description' => $request->asset_description,
            'asset_specification' => $request->asset_specification ?? null,
            'cellphone_number' => $request->cellphone_number ?? null,
            'brand' => $request->brand ?? null,
            'quantity' => $request->quantity,
        ]);
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

        //        foreach ($collections as $collection) {
        //            if (isset($request->$collection)) {
        //                $assetRequest->clearMediaCollection($collection);
        //                $assetRequest->addMultipleMediaFromRequest([$collection], $collection)->each(function ($fileAdder) use ($collection) {
        //                    $fileAdder->toMediaCollection($collection);
        //                });
        //            } else {
        //                $assetRequest->clearMediaCollection($collection);
        //            }
        //        }

        foreach ($collections as $collection) {
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
        }
    }

    /**
     * This is for the asset request index page.
     * Handles asset requests.
     *
     * @param Request $request The request object.
     * @return LengthAwarePaginator|array The paginated asset requests or array of asset requests.
     */
    public function transformIndexAssetRequest($assetRequest)
    {
        return [
            'id' => $assetRequest->transaction_number,
            'transaction_number' => $assetRequest->transaction_number,
            'item_count' => $assetRequest->quantity ?? 0,
            'date_requested' => $this->getDateRequested($assetRequest->transaction_number),
            'remarks' => $assetRequest->remarks ?? '',
            'status' => $assetRequest->status == 'Approved' ? $this->getAfterApprovedStatus($assetRequest) : $assetRequest->status,
            'pr_number' => $assetRequest->pr_number ?? '-',
            'acquisition_details' => $assetRequest->acquisition_details ?? '-',
            'created_at' => $this->getDateRequested($assetRequest->transaction_number),
            'approver_count' => $assetRequest->assetApproval->count(),
            'process_count' => $this->getProcessCount($assetRequest),
            'current_approver' => $assetRequest->assetApproval->filter(function ($approval) {
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
            })->values()->first() ?? $this->getAfterApprovedStep($assetRequest),
            'requestor' => [
                'id' => $assetRequest->requestor->id,
                'username' => $assetRequest->requestor->username,
                'employee_id' => $assetRequest->requestor->employee_id,
                'firstname' => $assetRequest->requestor->firstname,
                'lastname' => $assetRequest->requestor->lastname,
                'department' => $assetRequest->requestor->department->department_name ?? '-',
                'subunit' => $assetRequest->requestor->subUnit->sub_unit_name ?? '-',
            ],
            'history' => $assetRequest->activityLog->map(function ($activityLog) {
                return [
                    'id' => $activityLog->id,
                    'action' => $activityLog->log_name,
                    'causer' => $activityLog->causer,
                    'created_at' => $activityLog->created_at,
                    'remarks' => $activityLog->properties['remarks'] ?? null,
                ];
            }),
            'steps' => $this->getSteps($assetRequest),
        ];
    }

    private function getAfterApprovedStep($assetRequest)
    {
        //check if the status is approved
        $approvers = $assetRequest->status == 'Approved';
        if ($approvers) {
            //check if null pr number
            if ($assetRequest->pr_number == null) {
                return [
                    'firstname' => 'Inputing of PR No.',
                    'lastname' => '',
                ];
            }
            //check if null po number
            if ($assetRequest->po_number == null && $assetRequest->pr_number != null) {
                return [
                    'firstname' => 'Inputing of PO No.',
                    'lastname' => '',
                ];
            }

            if ($assetRequest->vladimir_tagNumber == null && $assetRequest->po_number != null && $assetRequest->pr_number != null) {
                return [
                    'firstname' => 'Asset Tagging',
                    'lastname' => '',
                ];
            }
        }

        return 'Something went wrong';
    }

    private function getAfterApprovedStatus($assetRequest): string
    {
        $approvers = $assetRequest->status == 'Approved';
        if ($approvers) {
            //check if null pr number
            if ($assetRequest->pr_number == null) {
                return 'Inputing of PR No.';
            }
            //check if null po number
            if ($assetRequest->po_number == null && $assetRequest->pr_number != null) {
                return 'Inputing of PO No.';
            }

            if ($assetRequest->vladimir_tagNumber == null && $assetRequest->po_number != null && $assetRequest->pr_number != null) {
                return 'Asset Tagging';
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
        $steps[] = 'Matching of PR No. to Receiving';
        $steps[] = 'Asset Tagging';
        $steps[] = 'Ready to Pickup';
        $steps[] = 'Released';

        return $steps;
    }

    private function getProcessCount($assetRequest)
    {
        $statusForApproval = $assetRequest->assetApproval->where('status', 'For Approval');
        $highestLayerNumber = $assetRequest->assetApproval()->max('layer');
        $statusForApprovalCount = $statusForApproval->count();
        $returnStatus = $assetRequest->assetApproval->where('status', 'Returned')->count();

        if ($statusForApprovalCount > 0) {
            $lastLayer = $statusForApproval->first()->layer;
        } elseif ($returnStatus > 0) {
            $lastLayer = 1;
        } else {
            $lastLayer = $highestLayerNumber ?? 0;

            if ($assetRequest->pr_number == null) $lastLayer++;
            if ($assetRequest->po_number == null && $assetRequest->pr_number != null) $lastLayer += 2;
            if ($assetRequest->vladimir_tagNumber == null && $assetRequest->po_number != null && $assetRequest->pr_number != null) $lastLayer += 3;
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


    /**
     * This is for the asset request show page.
     *
     * */
    public function transformShowAssetRequest($assetRequest)
    {
        return $assetRequest->transform(function ($ar) {

            /*
             * //TODO: This is the viewing if the attachments are multiple
             * $letterOfRequestMedia = $ar->getMedia('letter_of_request')->all();
             * */

            $letterOfRequestMedia = $ar->getMedia('letter_of_request')->first();
            $quotationMedia = $ar->getMedia('quotation')->first();
            $specificationFormMedia = $ar->getMedia('specification_form')->first();
            $toolOfTradeMedia = $ar->getMedia('tool_of_trade')->first();
            $otherAttachmentsMedia = $ar->getMedia('other_attachments')->first();

            return [
                'can_edit' => $ar->status == 'Returned' || $ar->status == 'For Approval of Approver 1'  ? 1 : 0,
                'can_resubmit' => $ar->status == 'Returned'  ? 1 : 0,
                'id' => $ar->id,
                'status' => $ar->status,
                'transaction_number' => $ar->transaction_number,
                'reference_number' => $ar->reference_number,
                'pr_number' => $ar->pr_number,
                'po_number' => $ar->po_number,
                'attachment_type' => $ar->attachment_type,
                'remarks' => $ar->remarks ?? '',
                'accountability' => $ar->accountability,
                'accountable' => $ar->accountable ?? '-',
                'additional_info' => $ar->additional_info ?? '-',
                'acquisition_details' => $ar->acquisition_details ?? '-',
                'asset_description' => $ar->asset_description,
                'asset_specification' => $ar->asset_specification ?? '-',
                'cellphone_number' => $ar->cellphone_number ?? '-',
                'brand' => $ar->brand ?? '-',
                'quantity' => $ar->quantity,
                'requestor' => [
                    'id' => $ar->requestor->id,
                    'username' => $ar->requestor->username,
                    'employee_id' => $ar->requestor->employee_id,
                    'firstname' => $ar->requestor->firstname,
                    'lastname' => $ar->requestor->lastname,
                ],
                'type_of_request' => [
                    'id' => $ar->typeOfRequest->id,
                    'type_of_request_name' => $ar->typeOfRequest->type_of_request_name,
                ],
                'company' => [
                    'id' => $ar->company->id,
                    'company_code' => $ar->company->company_code,
                    'company_name' => $ar->company->company_name,
                ],
                'department' => [
                    'id' => $ar->department->id,
                    'department_code' => $ar->department->department_code,
                    'department_name' => $ar->department->department_name,
                ],
                'subunit' => [
                    'id' => $ar->subunit->id,
                    'subunit_code' => $ar->subunit->sub_unit_code,
                    'subunit_name' => $ar->subunit->sub_unit_name,
                ],
                'location' => [
                    'id' => $ar->location->id,
                    'location_code' => $ar->location->location_code,
                    'location_name' => $ar->location->location_name,
                ],
                'account_title' => [
                    'id' => $ar->accountTitle->id,
                    'account_title_code' => $ar->accountTitle->account_title_code,
                    'account_title_name' => $ar->accountTitle->account_title_name,
                ],
                'attachments' => [
                    /*
                     * TODO: This is the viewing if the attachments are multiple
                      $letterOfRequestMedia ? collect($letterOfRequestMedia)->map(function ($media) {
                          return [
                              'id' => $media->id,
                              'file_name' => $media->file_name,
                              'file_path' => $media->getPath(),
                              'file_url' => $media->getUrl(),
                          ];
                      }) : '-',
                    */
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
                ]
            ];
        });
    }

    public function transformForSingleItemOnly($assetRequest): array
    {
        return [
            'id' => $assetRequest->id,
            'status' => $assetRequest->status,
            'transaction_number' => $assetRequest->transaction_number,
            'reference_number' => $assetRequest->reference_number,
            'pr_number' => $assetRequest->pr_number,
            'po_number' => $assetRequest->po_number,
            'attachment_type' => $assetRequest->attachment_type,
            'remarks' => $assetRequest->remarks ?? '',
            'additional_info' => $assetRequest->additional_info ?? '-',
            'acquisition_details' => $assetRequest->acquisition_details ?? '-',
            'accountability' => $assetRequest->accountability,
            'accountable' => $assetRequest->accountable ?? '-',
            'asset_description' => $assetRequest->asset_description,
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

    public function voidRequestItem($referenceNumber, $transactionNumber): JsonResponse
    {

        $approverId = $this->isUserAnApprover($transactionNumber);

        // return $this->responseUnprocessable($assetRequest);
        if (!$approverId) {
            $assetRequest = $this->getAssetRequest('reference_number', $referenceNumber);
            if (!$assetRequest) {
                return $this->responseUnprocessable('Unable to void Request Item.');
            }
        } else {
            $assetRequest = $this->getAssetRequestForApprover('reference_number', $transactionNumber, $referenceNumber);
        }

        if ($this->requestCount($assetRequest->transaction_number) == 1) {
            $assetRequest->update([
                'status' => 'Void'
            ]);

            $this->updateToVoid($assetRequest->transaction_number, 'Void');

            $assetRequest->delete();

            return $this->responseSuccess('Asset Request voided Successfully');
        }

        $assetRequest->update([
            'status' => 'Void'
        ]);
        $assetRequest->delete();

        return $this->responseSuccess('Asset Request voided Successfully');
    }

    public function voidAssetRequest($transactionNumber)
    {
        // return $this->responseSuccess($this->isUserAnApprover($transactionNumber));

        $approverId = $this->isUserAnApprover($transactionNumber);

        // return $this->responseUnprocessable($assetRequest);
        if ($approverId == null) {
            $assetRequest = $this->getAssetRequest('transaction_number', $transactionNumber, false);
            if ($assetRequest->isEmpty()) {
                return $this->responseUnprocessable('Unable to void Asset Request.');
            }
        } else {
            $assetRequest = $this->getAssetRequestForApprover('transaction_number', $transactionNumber, null, false);
        }
        foreach ($assetRequest as $ar) {
            $ar->update([
                'status' => 'Void'
            ]);
            $ar->delete();
        }
        return $this->responseSuccess('Asset Request voided Successfully');
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

    public function updateToVoid($transactionNumber, $status)
    {

        return AssetApproval::where('transaction_number', $transactionNumber)
            ->update(['status' => $status]);
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
