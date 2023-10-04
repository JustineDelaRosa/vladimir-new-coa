<?php

namespace App\Transformers\AssetRequestTransformers;

use App\Models\AssetRequest;

class AssetRequestTransformers
{
    public function transform(AssetRequest $assetRequest)
    {
        return[
            'id' => $assetRequest->id,
            'status'=> $assetRequest->status,
            'requester' => [
                'id' => $assetRequest->requester->id,
                'username' => $assetRequest->requester->username,
                'employee_id' => $assetRequest->requester->employee_id,
                'firstname' => $assetRequest->requester->firstname,
                'lastname' => $assetRequest->requester->lastname,
            ],
            'type_of_request' => [
                'id' => $assetRequest->typeOfRequest->id,
                'type_of_request_name' => $assetRequest->typeOfRequest->type_of_request_name,
            ],
            'capex' => [
                'id' => $assetRequest->capex->id ?? '-',
                'capex_name' => $assetRequest->capex->capex_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $assetRequest->subCapex->id ?? '-',
                'sub_capex_name' => $assetRequest->subCapex->sub_capex_name ?? '-',
            ],
            'asset_description' => $assetRequest->asset_description,
            'asset_specification' => $assetRequest->asset_specification ?? '-',
            'accountability' => $assetRequest->accountability,
            'accountable' => $assetRequest->accountable ?? '-',
            'cellphone_number' => $assetRequest->cellphone_number ?? '-',
            'brand' => $assetRequest->brand ?? '-',
        ];
    }
}
