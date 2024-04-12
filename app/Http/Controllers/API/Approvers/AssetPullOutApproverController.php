<?php

namespace App\Http\Controllers\API\Approvers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UnitApproverBaseController;
use App\Http\Requests\FormSetting\AssetPullOutApprover\CreateAssetPullOutApproverRequest;
use App\Http\Requests\FormSetting\AssetTransferApprover\UpdateAssetTransferApproverRequest;
use App\Models\AssetPullOutApprover;
use Illuminate\Http\Request;

class AssetPullOutApproverController extends UnitApproverBaseController
{
    public function __construct()
    {
        parent::__construct(new AssetPullOutApprover);
    }

    protected function getCreateFormRequest()
    {
        return CreateAssetPullOutApproverRequest::class;
    }
    protected function getUpdateFormRequest()
    {
        return UpdateAssetTransferApproverRequest::class;
    }
}
