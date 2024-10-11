<?php

namespace App\Http\Controllers\API\AssetMovement;

use App\Http\Controllers\AssetMovementBaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetTransfer\CreateAssetTransferRequestRequest;
use App\Http\Resources\Capex\CapexResource;
use App\Models\AssetTransferApprover;
use App\Models\Disposal;
use App\Models\Pullout;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AssetTransferServices;
use App\Services\MovementApprovalServices;
use Illuminate\Http\Request;

class TransferController extends AssetMovementBaseController
{
    public function __construct(AssetTransferServices $assetTransferServices, MovementApprovalServices $movementApprovalServices)
    {
        parent::__construct(new Transfer(), $assetTransferServices, $movementApprovalServices);
    }

    protected function movementCreateFormRequest()
    {
        return CreateAssetTransferRequestRequest::class;
    }

    protected function movementUpdateFormRequest()
    {
     return CreateAssetTransferRequestRequest::class;
    }

}
