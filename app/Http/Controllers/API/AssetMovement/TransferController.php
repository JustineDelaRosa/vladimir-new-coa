<?php

namespace App\Http\Controllers\API\AssetMovement;

use App\Http\Controllers\AssetMovementBaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetTransfer\CreateAssetTransferRequestRequest;
use App\Http\Requests\AssetTransfer\UpdateAssetTransferRequestRequest;
use App\Http\Resources\Capex\CapexResource;
use App\Models\AssetTransferApprover;
use App\Models\Disposal;
use App\Models\FixedAsset;
use App\Models\MovementNumber;
use App\Models\Pullout;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AssetTransferServices;
use App\Services\MovementApprovalServices;
use App\Traits\AssetMovement\TransferHandler;
use Illuminate\Http\Request;

class TransferController extends AssetMovementBaseController
{
    use TransferHandler;

    public function __construct(AssetTransferServices $assetTransferServices, MovementApprovalServices $movementApprovalServices)
    {
        parent::__construct(new Transfer(), $assetTransferServices, $movementApprovalServices);
    }

//    public function store(){
//        return AssetTransferApprover::where('subunit_id', 49)->get();
//    }

    protected function movementCreateFormRequest()
    {
        return CreateAssetTransferRequestRequest::class;
    }

    protected function movementUpdateFormRequest()
    {
        return UpdateAssetTransferRequestRequest::class;
    }


    public function singleViewing($transferId)
    {
        $transfer = Transfer::find($transferId);
        $movementId = $transfer->movement_id;
        $fixedAssetId = $transfer->fixed_asset_id;
        if (!$transfer) {
            return $this->responseNotFound('No Data Found');
        }
        $fixedAsset = FixedAsset::where('id', $fixedAssetId)->first();
        $movementNumber = MovementNumber::find($movementId);
        if (!$fixedAsset || !$movementNumber || !$transfer) {
            return $this->responseNotFound('No Data Found');
        }

        return $this->transformSingleFixedAssetShowData($fixedAsset, $movementNumber, $transfer);
    }
}
