<?php

namespace App\Traits;

use App\Models\AccountTitle;
use App\Models\RequestContainer;
use Essa\APIToolKit\Api\ApiResponse;

trait RequestContainerHandler
{
    use ApiResponse;
    private function checkIfRequesterIsApprover($requesterId, $departmentUnitApprovers)
    {
        $layerIds = $departmentUnitApprovers->map(function ($approverObject) {
            return $approverObject->approver->approver_id;
        })->toArray();

        $isRequesterApprover = in_array($requesterId, $layerIds);
        $isLastApprover = false;
        $requesterLayer = 0;
        if ($isRequesterApprover) {
            $requesterLayer = array_search($requesterId, $layerIds) + 1;
            $maxLayer = $departmentUnitApprovers->max('layer');
            $isLastApprover = $maxLayer == $requesterLayer;
        }

        return [$isRequesterApprover, $isLastApprover, $requesterLayer];
    }

    private function createRequestContainer($request, $isRequesterApprover, $isLastApprover, $requesterLayer, $requesterId)
    {
        $assetClearing = AccountTitle::where('account_title_name', 'Asset Clearing')->first()->id;
        return RequestContainer::create([
            'status' => $isLastApprover
                ? 'Approved'
                : ($isRequesterApprover
                    ? 'For Approval of Approver ' . ($requesterLayer + 1)
                    : 'For Approval of Approver 1'),
            'requester_id' => $requesterId,
            'capex_number' => $request->capex_number,
            'is_addcost' => (bool)$request->fixed_asset_id,
            'fixed_asset_id' => $request->fixed_asset_id ?? null,
            'type_of_request_id' => $request->type_of_request_id,
            'attachment_type' => $request->attachment_type,
            'account_title_id' => $assetClearing,
            'accountability' => $request->accountability,
            'accountable' => $request->accountable ?? null,
            'additional_info' => $request->additional_info ?? null,
            'acquisition_details' => $request->acquisition_details,
            'asset_description' => $request->asset_description,
            'asset_specification' => $request->asset_specification ?? null,
            'cellphone_number' => $request->cellphone_number ?? null,
            'brand' => $request->brand ?? null,
            'quantity' => $request->quantity,
            'date_needed' => $request->date_needed,
            'company_id' => $request->company_id,
            'business_unit_id' => $request->business_unit_id,
            'department_id' => $request->department_id,
            'unit_id' => $request->unit_id,
            'subunit_id' => $request->subunit_id,
            'location_id' => $request->location_id,
            'uom_id' => $request->uom_id,
            'receiving_warehouse_id' => $request->receiving_warehouse_id,
        ]);
    }

    private function addMediaToRequestContainer($request, $assetRequest)
    {
        $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];

        foreach ($fileKeys as $fileKey) {
            if (isset($request->$fileKey)) {
                $files = is_array($request->$fileKey) ? $request->$fileKey : [$request->$fileKey];
                foreach ($files as $file) {
                    $assetRequest->addMedia($file)->toMediaCollection($fileKey);
                }
            }
        }
    }

    private function checkDifferentCOA($request)
    {
        $requesterId = auth('sanctum')->user()->id;
        $requestContainer = RequestContainer::where('requester_id', $requesterId)->get();
        if ($requestContainer->isNotEmpty()) {
            $this->differentFixedAssetId($request, $requestContainer, $requesterId);
            $this->updateRequestContainer($request, $requestContainer);
        }
        return;
    }

    private function updateRequestContainer($request, $requestContainer)
    {
        if ($requestContainer->first()->subunit_id != $request->subunit_id) {
            foreach ($requestContainer as $requestContainerItem) {
                $requestContainerItem->update([
                    'company_id' => $request->company_id,
                    'business_unit_id' => $request->business_unit_id,
                    'department_id' => $request->department_id,
                    'unit_id' => $request->unit_id,
                    'subunit_id' => $request->subunit_id,
                    'location_id' => $request->location_id,
                    'account_title_id' => $request->account_title_id,
                ]);
            }
        } elseif ($requestContainer->first()->acquisition_details != $request->acquisition_details) {
            foreach ($requestContainer as $requestContainerItem) {
                $requestContainerItem->update([
                    'acquisition_details' => $request->acquisition_details,
                ]);
            }
        } elseif ($requestContainer->first()->fixed_asset_id != $request->fixed_asset_id && $request->fixed_asset_id != null) {
            foreach ($requestContainer as $requestContainerItem) {
                $requestContainerItem->update([
                    'fixed_asset_id' => $request->fixed_asset_id,
                ]);
            }
        }
    }

    private function updateStatusIfDifferent($newStatus)
    {
        $secondLatestRequestContainer = RequestContainer::latest()->skip(1)->first();
        $requester = auth('sanctum')->user();

        if ($secondLatestRequestContainer && $secondLatestRequestContainer->status !== $newStatus) {

            RequestContainer::where('requester_id', $requester->id)->update([
                'status' => $newStatus,
            ]);
        }
    }

    private function differentFixedAssetId($request, $requestContainer, $requesterId)
    {
        if ($requestContainer->first()->fixed_asset_id != $request->fixed_asset_id && $request->fixed_asset_id != null) {
            return $this->responseUnprocessable('Different Fixed Asset Id');
        }
    }
}
