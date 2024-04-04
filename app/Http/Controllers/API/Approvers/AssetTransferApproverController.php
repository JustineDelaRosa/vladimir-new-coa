<?php

namespace App\Http\Controllers\API\Approvers;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormSetting\AssetTransferApprover\CreateAssetTransferApproverRequest;
use App\Http\Requests\FormSetting\AssetTransferApprover\UpdateAssetTransferApproverRequest;
use App\Models\AssetTransferApprover;
use App\Models\DepartmentUnitApprovers;
use App\Traits\FormSettingHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class AssetTransferApproverController extends Controller
{
    use ApiResponse,FormSettingHandler;

    public function index(Request $request)
    {
        return $this->formSettingIndex($request, new AssetTransferApprover());
    }


    public function store(CreateAssetTransferApproverRequest $request)
    {
        return $this->formSettingStore($request, new AssetTransferApprover());
    }

    public function show($id)
    {
        //
    }

    public function update(UpdateAssetTransferApproverRequest $request, $id)
    {
        //
    }

    public function destroy($subUnitId)
    {
        return $this->formSettingDestroy(new AssetTransferApprover(), $subUnitId);
    }

    public function arrangeLayer(UpdateAssetTransferApproverRequest $request, $id)
    {
        return $this->formSettingArrangeLayer($request, new AssetTransferApprover(), $id);
    }
}
