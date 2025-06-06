<?php

namespace App\Traits;

use App\Models\AccountingEntries;
use App\Models\AccountTitle;
use App\Models\MinorCategory;
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

    private function createRequestContainer($request, $isRequesterApprover, $isLastApprover, $requesterLayer, $requesterId, $accountingEntriesId)
    {
//        $accountTitleID = MinorCategory::with('accountTitle')->where('id', $request->minor_category_id)->first()->accountingEntries->initialCredit->id ?? "null";
//        $accountTitleID = MinorCategory::with('accountTitle')->where('id', $request->minor_category_id)->first()->accountTitle->id ?? "null";
        return RequestContainer::create([
            'status' => $isLastApprover
                ? 'Approved'
                : ($isRequesterApprover
                    ? 'For Approval of Approver ' . ($requesterLayer + 1)
                    : 'For Approval of Approver 1'),
            'requester_id' => $requesterId,
            'capex_number' => $request->capex_number,
            'is_addcost' => $request->is_addcost,
            'fixed_asset_id' => $request->fixed_asset_id ?? null,
            'item_status' => $request->item_status,
            'item_id' => $request->item_id ?? null,
//            'small_tool_id' => $request->small_tool_id ?? null,
            'type_of_request_id' => $request->type_of_request_id,
            'attachment_type' => $request->attachment_type,
            'account_title_id' => $accountingEntriesId ?? null,
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
            'major_category_id' => $request->major_category_id,
            'minor_category_id' => $request->minor_category_id,
            'one_charging_id' => $request->one_charging_id,
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

    private function creatAccountingEntries($initialDebitId, $depreciationCreditId, $request, $isRequesterApprover, $isLastApprover, $requesterLayer, $requesterId)
    {
        $accountingEntries = AccountingEntries::create([
            'initial_debit' => $initialDebitId,
            'depreciation_credit' => $depreciationCreditId,
        ]);

        $assetRequest = $this->createRequestContainer($request, $isRequesterApprover, $isLastApprover, $requesterLayer, $requesterId, $accountingEntries->id);

        $this->addMediaToRequestContainer($request, $assetRequest);
//            return $assetRequest->status;
        $this->updateStatusIfDifferent($assetRequest->status);

        return $assetRequest;
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
        if (($requestContainer->first()->subunit_id != $request->subunit_id) || ($requestContainer->first()->one_charging_id != $request->one_charging_id)) {
            foreach ($requestContainer as $requestContainerItem) {
                $requestContainerItem->update([
                    'one_charging_id' => $request->one_charging_id,
                    'company_id' => $request->company_id,
                    'business_unit_id' => $request->business_unit_id,
                    'department_id' => $request->department_id,
                    'unit_id' => $request->unit_id,
                    'subunit_id' => $request->subunit_id,
                    'location_id' => $request->location_id,
                    'account_title_id' => $request->account_title_id,
                ]);
            }
        }
        if ($requestContainer->first()->acquisition_details != $request->acquisition_details) {
            foreach ($requestContainer as $requestContainerItem) {
                $requestContainerItem->update([
                    'acquisition_details' => $request->acquisition_details,
                ]);
            }
        }
        if ($requestContainer->first()->fixed_asset_id != $request->fixed_asset_id && $request->fixed_asset_id != null) {
            foreach ($requestContainer as $requestContainerItem) {
                $requestContainerItem->update([
                    'fixed_asset_id' => $request->fixed_asset_id,
                ]);
            }
        }
        if ($requestContainer->first()->receiving_warehouse_id != $request->receiving_warehouse_id) {
            foreach ($requestContainer as $requestContainerItem) {
                $requestContainerItem->update([
                    'receiving_warehouse_id' => $request->receiving_warehouse_id,
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
