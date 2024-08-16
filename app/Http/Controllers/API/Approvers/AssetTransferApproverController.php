<?php

namespace App\Http\Controllers\API\Approvers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UnitApproverBaseController;
use App\Http\Requests\FormSetting\AssetTransferApprover\CreateAssetTransferApproverRequest;
use App\Http\Requests\FormSetting\AssetTransferApprover\UpdateAssetTransferApproverRequest;
use App\Models\AssetTransferApprover;
use App\Models\DepartmentUnitApprovers;
use App\Services\AssetTransferServices;
use App\Traits\FormSettingHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class AssetTransferApproverController extends UnitApproverBaseController
{
    protected AssetTransferServices $assetTransferServices;
    public function __construct(AssetTransferServices $assetTransferServices)
    {
        parent::__construct(
            new AssetTransferApprover,
            $assetTransferServices
        );
    }
    protected function getCreateFormRequest()
    {
        return CreateAssetTransferApproverRequest::class;
    }
    protected function getUpdateFormRequest()
    {
        return UpdateAssetTransferApproverRequest::class;
    }
}
