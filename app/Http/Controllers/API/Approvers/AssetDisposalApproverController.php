<?php

namespace App\Http\Controllers\API\Approvers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UnitApproverBaseController;
use App\Http\Requests\FormSetting\AssetDisposalApprover\CreateAssetDisposalApproverRequest;
use App\Http\Requests\FormSetting\AssetDisposalApprover\UpdateAssetDisposalApproverRequest;
use App\Models\AssetDisposalApprover;
use Illuminate\Http\Request;

class AssetDisposalApproverController extends UnitApproverBaseController
{
    public function __construct()
    {
        parent::__construct(new AssetDisposalApprover);
    }

    protected function getCreateFormRequest()
    {
        return CreateAssetDisposalApproverRequest::class;
    }
    protected function getUpdateFormRequest()
    {
        return UpdateAssetDisposalApproverRequest::class;
    }
}
