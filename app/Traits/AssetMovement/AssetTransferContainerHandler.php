<?php

namespace App\Traits\AssetMovement;

use App\Models\AssetMovementContainer\AssetTransferContainer;
use Illuminate\Pagination\LengthAwarePaginator;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Support\Collection;

trait AssetTransferContainerHandler
{
    use ApiResponse;

    private function setContainerResponse($requestTransferContainer)
    {
        if ($requestTransferContainer instanceof Collection) {
            return $this->collectionData($requestTransferContainer);
        } elseif ($requestTransferContainer instanceof LengthAwarePaginator) {
            $requestTransferContainer->getCollection()->transform(function ($item) {
                return $this->transformItem($item);
            });
            return $requestTransferContainer;
        } else {
            return $this->nonCollectionData($requestTransferContainer);
        }
    }

    private function collectionData($data)
    {
        return $data->transform(function ($ar) {
            return $this->response($ar);
        });
    }

    private function nonCollectionData($data)
    {
        return $data->getCollection()->transform(function ($ar) {
            return $this->response($ar);
        });
    }

    private function transformItem($ar): array
    {
        return $this->response($ar);
    }

    private function response1($ar)
    {
        $attachments = $ar->getMedia('attachments')->all();
        return [
            'id' => $ar->id,
            'created_by_id' => [
                'id' => $ar->createdBy->id,
                'firstname' => $ar->createdBy->firstname,
                'lastname' => $ar->createdBy->lastname,
                'employee_id' => $ar->createdBy->employee_id,
            ],
            'asset_description' => $ar->asset_description,
            'asset_specification' => $ar->asset_specification,
            'accountability' => $ar->accountability,
            'accountable' => $ar->accountable,
            'old' => [
                'company_id' => $ar->fixedAsset->company->id,
                'company_code' => $ar->fixedAsset->company->company_code,
                'company_name' => $ar->fixedAsset->company->company_name,

                'business_unit_id' => $ar->fixedAsset->businessUnit->id,
                'business_unit_code' => $ar->fixedAsset->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $ar->fixedAsset->businessUnit->business_unit_name ?? '-',

                'department_id' => $ar->fixedAsset->department->id,
                'department_code' => $ar->fixedAsset->department->department_code,
                'department_name' => $ar->fixedAsset->department->department_name,
                'sync_id' => $ar->fixedAsset->department->sync_id,

                'unit_id' => $ar->fixedAsset->unit->id,
                'unit_code' => $ar->fixedAsset->unit->unit_code,
                'unit_name' => $ar->fixedAsset->unit->unit_name,


                'subunit_id' => $ar->fixedAsset->subunit->id,
                'subunit_code' => $ar->fixedAsset->subunit->sub_unit_code,
                'subunit_name' => $ar->fixedAsset->subunit->sub_unit_name,


                'location_id' => $ar->fixedAsset->location->id,
                'location_code' => $ar->fixedAsset->location->location_code,
                'location_name' => $ar->fixedAsset->location->location_name,


                'account_title_id' => $ar->fixedAsset->accountTitle->id ?? '-',
                'account_title_code' => $ar->fixedAsset->accountTitle->account_title_code ?? '-',
                'account_title_name' => $ar->fixedAsset->accountTitle->account_title_name ?? '-',

            ],
            'new' => [

                'company_id' => $ar->company->id,
                'company_code' => $ar->company->company_code,
                'company_name' => $ar->company->company_name,


                'business_unit_id' => $ar->businessUnit->id,
                'business_unit_code' => $ar->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $ar->businessUnit->business_unit_name ?? '-',


                'department_id' => $ar->department->id,
                'department_code' => $ar->department->department_code,
                'department_name' => $ar->department->department_name,
                'sync_id' => $ar->department->sync_id,


                'unit_id' => $ar->unit->id,
                'unit_code' => $ar->unit->unit_code,
                'unit_name' => $ar->unit->unit_name,


                'subunit_id' => $ar->subunit->id,
                'subunit_code' => $ar->subunit->sub_unit_code,
                'subunit_name' => $ar->subunit->sub_unit_name,


                'location_id' => $ar->location->id,
                'location_code' => $ar->location->location_code,
                'location_name' => $ar->location->location_name,


                'account_title_id' => $ar->accountTitle->id ?? '-',
                'account_title_code' => $ar->accountTitle->account_title_code ?? '-',
                'account_title_name' => $ar->accountTitle->account_title_name ?? '-',

            ],

//            'supplier' => [
//                'id' => $ar->supplier->id ?? '-',
//                'supplier_code' => $ar->supplier->supplier_code ?? '-',
//                'supplier_name' => $ar->supplier->supplier_name ?? '-',
//            ],
            'remarks' => $ar->remarks,
            'attachments' => [
                'attachments' => $attachments ? collect($attachments)->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'name' => $attachment->file_name,
                        'url' => $attachment->getUrl(),
                    ];
                }) : collect([]),
            ],
            'created_at' => $ar->created_at,
        ];
    }

    private function response($ar){
        return [
            'transfer_number' => $ar->transfer_number,
            'description' => $ar->description,
            'fixed_asset' =>[
                'vladimir_tag_number' => $ar->fixedAsset->vladimir_tag_number,
                'description' => $ar->fixedAsset->description,
                'accountability' => $ar->fixedAsset->accountability,
                'accountable' => $ar->fixedAsset->accountable?? '-',
            ],
            'quantity' => $ar->fixedAsset->quantity,
            'requester' => [
                'id' => $ar->createdBy->id,
                'first_name' => $ar->createdBy->firstname,
                'last_name' => $ar->createdBy->lastname,
                'employee_id' => $ar->createdBy->employee_id,
                'username' => $ar->createdBy->username,
            ],
            'status' => $ar->status,
            'company' => [
                'id' => $ar->company->id ?? '-',
                'company_code' => $ar->company->company_code ?? '-',
                'company_name' => $ar->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $ar->businessUnit->id ?? '-',
                'business_unit_code' => $ar->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $ar->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $ar->department->id ?? '-',
                'department_code' => $ar->department->department_code ?? '-',
                'department_name' => $ar->department->department_name ?? '-',
            ],
            'unit' => [
                'id' => $ar->unit->id ?? '-',
                'unit_code' => $ar->unit->unit_code ?? '-',
                'unit_name' => $ar->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $ar->subunit->id ?? '-',
                'subunit_code' => $ar->subunit->sub_unit_code ?? '-',
                'subunit_name' => $ar->subunit->sub_unit_name ?? '-',
            ],
            'location' => [
                'id' => $ar->location->id ?? '-',
                'location_code' => $ar->location->location_code ?? '-',
                'location_name' => $ar->location->location_name ?? '-',
            ],
            'created_at' => $ar->created_at,
        ];
    }


    public function checkIfRequesterIsApprover($requesterId, $transferApprovers)
    {
        $layerIds = $transferApprovers->map(function ($approverObject) {
            return $approverObject->approver->approver_id;
        })->toArray();

        $isRequesterApprover = in_array($requesterId, $layerIds);
        $isLastApprover = false;
        $requesterLayer = 0;
        if ($isRequesterApprover) {
            $requesterLayer = array_search($requesterId, $layerIds) + 1;
            $maxLayer = $transferApprovers->max('layer');
            $isLastApprover = $maxLayer == $requesterLayer;
        }

        return [$isRequesterApprover, $isLastApprover, $requesterLayer];
    }


    public function checkDifferentCOA($request)
    {
        $requesterId = auth('sanctum')->user()->id;
        $transferContainer = AssetTransferContainer::where('created_by_id', $requesterId)->get();
        if($transferContainer->isNotEmpty()){
            $this->updateRequestContainer($request, $transferContainer);
        }
    }

    public function updateRequestContainer($request, $transferContainer)
    {
        if($transferContainer->first()->subunit_id != $request->subunit_id){
            foreach($transferContainer as $container){
                $container->update([
                    'company_id' => $request->company_id,
                    'business_unit_id' => $request->business_unit_id,
                    'department_id' => $request->department_id,
                    'unit_id' => $request->unit_id,
                    'subunit_id' => $request->subunit_id,
                    'location_id' => $request->location_id,
                    'account_id' => $request->account_title_id,
                ]);
            }
        }
    }
}
