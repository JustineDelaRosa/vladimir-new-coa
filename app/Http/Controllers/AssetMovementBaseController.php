<?php

namespace App\Http\Controllers;

use App\Models\Approvers;
use App\Models\Disposal;
use App\Models\MovementApproval;
use App\Models\MovementNumber;
use App\Models\Pullout;
use App\Models\Transfer;
use App\Services\AssetTransferServices;
use App\Services\MovementApprovalServices;
use App\Traits\ReusableFunctions\Reusables;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class AssetMovementBaseController extends Controller
{
    use ApiResponse, Reusables;

    protected $model;
    protected AssetTransferServices $assetTransferServices;
    protected MovementApprovalServices $movementApprovalServices;

    public function __construct($model, AssetTransferServices $assetTransferServices, MovementApprovalServices $movementApprovalServices)
    {
        $this->model = $model;
        $this->assetTransferServices = $assetTransferServices;
        $this->movementApprovalServices = $movementApprovalServices;
    }

    protected function movementCreateFormRequest()
    {
        return FormRequest::class;
    }

    protected function movementUpdateFormRequest()
    {
        return FormRequest::class;
    }


    public function index(Request $request)
    {
        //check if the $model is the instance of Transfer or pullout or disposal
        $instanceOf = $this->model;

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transfer';
                return $this->assetTransferServices->getTransfers($request, 'transfer');
                break;
            case $instanceOf instanceof Pullout:
                return 'pullout';
                return $this->assetTransferServices->getPullouts();
                break;
            case $instanceOf instanceof Disposal:
                return 'disposal';
                return $this->assetTransferServices->getDisposals();
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }

    }

    public function store()
    {
        $instanceOf = $this->model;
        $formRequestClass = $this->movementCreateFormRequest();
        $request = app($formRequestClass);

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transferstore';
                return $this->assetTransferServices->storeTransfer($request);
                break;
            case $instanceOf instanceof Pullout:
                return 'pullout';
                return $this->assetTransferServices->storePullouts();
                break;
            case $instanceOf instanceof Disposal:
                return 'disposal';
                return $this->assetTransferServices->storeDisposals();
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }
    }

    public function handleMovement(Request $request)
    {
        $userId = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $userId)->first()->id;
        $instanceOf = $this->model;
        $action = $request->action;
        $movementId = $request->movement_id;


        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transferstore';
                return $this->movementApprovalServices->movementApproval($action, $movementId, $approverId);
                break;
            case $instanceOf instanceof Pullout:
                return 'pullout';
                return $this->assetTransferServices->storePullouts();
                break;
            case $instanceOf instanceof Disposal:
                return 'disposal';
                return $this->assetTransferServices->storeDisposals();
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }
    }

    public function approvalViewing(Request $request)
    {
        $userId = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $userId)->first()->id;
        $instanceOf = $this->model;

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transfer';
                return $this->movementApprovalServices->approverViewing($request, 'transfer');
                break;
            case $instanceOf instanceof Pullout:
//                return 'pullout';
                return $this->movementApprovalServices->approverViewing($request, 'pullout');
                break;
            case $instanceOf instanceof Disposal:
//                return 'disposal';
                return $this->movementApprovalServices->approverViewing($request, 'pullout');
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }
    }


}
