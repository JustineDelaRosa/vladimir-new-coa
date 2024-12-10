<?php

namespace App\Http\Controllers;

use App\Models\Approvers;
use App\Models\Disposal;
use App\Models\MovementApproval;
use App\Models\MovementNumber;
use App\Models\PullOut;
use App\Models\Transfer;
use App\Services\AssetDisposalServices;
use App\Services\AssetPullOutServices;
use App\Services\AssetTransferServices;
use App\Services\MovementApprovalServices;
use App\Traits\ReusableFunctions\Reusables;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class AssetMovementBaseController extends Controller
{
    use ApiResponse, Reusables;

    protected $model;
//    protected AssetTransferServices $assetTransferServices;
    protected $assetService;
    protected MovementApprovalServices $movementApprovalServices;

    public function __construct($model, $assetService, MovementApprovalServices $movementApprovalServices)
    {
        $this->model = $model;
        $this->movementApprovalServices = $movementApprovalServices;

        if ($assetService instanceof AssetTransferServices) {
            $this->assetService = $assetService;
        } elseif ($assetService instanceof AssetPullOutServices) {
            $this->assetService = $assetService;
        } elseif ($assetService instanceof AssetDisposalServices) {
            $this->assetService = $assetService;
        } else {
            throw new \InvalidArgumentException('Invalid asset service provided');
        }
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
                return $this->assetService->getTransfers($request, 'transfer');
                break;
            case $instanceOf instanceof PullOut:
//                return 'pullout';
                return $this->assetService->getPullouts($request, 'pullout');
                break;
            case $instanceOf instanceof Disposal:
                return 'disposal';
                return $this->assetService->getDisposals();
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
                return $this->assetService->storeTransfer($request);
                break;
            case $instanceOf instanceof PullOut:
//                return 'pullout';
                return $this->assetService->storePullOut($request);
                break;
            case $instanceOf instanceof Disposal:
                return 'disposal';
                return $this->assetService->storeDisposals();
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }
    }

    public function handleMovement(Request $request)
    {
        $userId = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $userId)->first()->id ?? '';
        if (!$approverId) {
            return $this->responseUnprocessable('Invalid User');
        }
        $instanceOf = $this->model;
        $action = $request->action;
        $reason = $request->remarks;
        $movementId = $request->movement_id;

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transfer';
                return $this->movementApprovalServices->movementApproval($action, $movementId, $approverId, $reason);
                break;
            case $instanceOf instanceof PullOut:
//                return 'pullout';
                return $this->movementApprovalServices->movementApproval($action, $movementId, $approverId, $reason);
                break;
            case $instanceOf instanceof Disposal:
//                return 'disposal';
                return $this->movementApprovalServices->movementApproval($action, $movementId, $approverId, $reason);
                break;
            default:
                return $this->responseUnprocessable('Invalid data');

        }

    }

    public function approvalViewing(Request $request)
    {
//        $userId = auth('sanctum')->user()->id;
//        $approverId = Approvers::where('approver_id', $userId)->first()->id;
        $instanceOf = $this->model;

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transfer';
                return $this->movementApprovalServices->approverViewing($request, 'transfer');
                break;
            case $instanceOf instanceof PullOut:
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

    public function show(Request $request, $movementId)
    {
        $instanceOf = $this->model;
        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transfer';
                return $this->assetService->showTransfer($movementId, $request);
                break;
            case $instanceOf instanceof PullOut:
//                return 'pullout';
                return $this->assetService->showPullOut($movementId, $request);
                break;
            case $instanceOf instanceof Disposal:
//                return 'disposal';
                return $this->assetService->showDisposal($movementId);
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }
    }

    public function movementUpdate($movementId)
    {
        $instanceOf = $this->model;
        $formRequestClass = $this->movementUpdateFormRequest();
        $request = app($formRequestClass);

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transfer';
                return $this->assetService->updateTransfer($request, $movementId);
                break;
            case $instanceOf instanceof PullOut:
//                return 'pullout';
                return $this->assetService->updatePullOut($request, $movementId);
                break;
            case $instanceOf instanceof Disposal:
//                return 'disposal';
                return $this->assetService->updateDisposal($request, $movementId);
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }
    }

    public function nextToApproved(Request $request)
    {
        $instanceOf = $this->model;
        $userId = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $userId)->first()->id ?? '';
        if (!$approverId) {
            return $this->responseUnprocessable('Invalid User');
        }

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transfer';
                return $this->assetService->getNextTransferRequest($request, $approverId);
                break;
            case $instanceOf instanceof PullOut:
//                return 'pullout';
                return $this->assetService->getNextPullOutRequest($request, $approverId);
                break;
            case $instanceOf instanceof Disposal:
//                return 'disposal';
                return $this->assetService->getNextDisposalRequest($approverId);
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }
    }

    public function voidMovement(Request $request)
    {
        $instanceOf = $this->model;
        $movementId = $request->movement_id;
        $userId = auth('sanctum')->user()->id;
//        $approverId = Approvers::where('approver_id', $userId)->first()->id ?? '';
//        if (!$approverId) {
//            return $this->responseUnprocessable('Invalid User');
//        }

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
//                return 'transfer';
                return $this->assetService->voidTransfer($movementId);
                break;
            case $instanceOf instanceof PullOut:
//                return 'pullout';
                return $this->assetService->voidPullOut($movementId);
                break;
            case $instanceOf instanceof Disposal:
//                return 'disposal';
                return $this->assetService->voidDisposal($movementId, $approverId);
                break;
            default:
                return $this->responseUnprocessable('Invalid data');

        }

    }

    public function movementMediaDownload($movementId)
    {
        //check first is the user is auth using sanctum
//        $userId = auth('sanctum')->user()->id;
//        if (!$userId) {
//            return $this->responseUnauthorized('Unauthorized');
//        }

        $movementNumber = MovementNumber::withTrashed()->where('id', $movementId)->first();
        //create a temporary zip file
        $zipFile = tempnam(sys_get_temp_dir(), 'attachments') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE);

        //get the media files
        $mediaItems = $movementNumber->getMedia('attachments');
        if ($mediaItems->isEmpty()) {
            $zip->close();
            return $this->responseNotFound('No media files found');
        }
        foreach ($mediaItems as $mediaItem) {
            $zip->addFile($mediaItem->getPath(), $mediaItem->file_name);
        }

        //close the zip file
        $zip->close();

        //return the zip file
        return response()->download($zipFile, 'attachments.zip')
            ->deleteFileAfterSend(true);
    }

    public function movementConfirmation(Request $request)
    {
        $instanceOf = $this->model;

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
                return $this->assetService->transferConfirmation($request);
                break;
            case $instanceOf instanceof PullOut:
                return $this->assetService->pulloutConfirmation($request);
                break;
            case $instanceOf instanceof Disposal:
                return $this->assetService->disposalConfirmation($request);
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }

    }

    public function movementReceiverViewing(Request $request)
    {
        $instanceOf = $this->model;

        switch ($instanceOf) {
            case $instanceOf instanceof Transfer:
                return $this->assetService->transferReceiverView($request);
                break;
            case $instanceOf instanceof PullOut:
                return $this->movementApprovalServices->pulloutViewing();
                break;
            case $instanceOf instanceof Disposal:
                return $this->movementApprovalServices->disposalViewing();
                break;
            default:
                return $this->responseUnprocessable('Invalid data');
        }
    }
}
