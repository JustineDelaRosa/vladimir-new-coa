<?php

namespace App\Http\Controllers\API\Approvers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UnitApproverBaseController;
use App\Http\Requests\FormSetting\AssetTransferApprover\CreateAssetTransferApproverRequest;
use App\Http\Requests\FormSetting\AssetTransferApprover\UpdateAssetTransferApproverRequest;
use App\Models\AssetTransferApprover;
use App\Models\DepartmentUnitApprovers;
use App\Traits\FormSettingHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class AssetTransferApproverController extends UnitApproverBaseController
{
    public function __construct()
    {
        parent::__construct(
            new AssetTransferApprover,
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
