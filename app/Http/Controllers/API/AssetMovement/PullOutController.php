<?php

namespace App\Http\Controllers\API\AssetMovement;

use App\Http\Controllers\AssetMovementBaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetPullOut\CreateAssetPullOutRequest;
use App\Http\Requests\AssetPullOut\UpdateAssetPullOutRequest;
use App\Models\PullOut;
use App\Models\SubUnit;
use App\Services\AssetPullOutServices;
use App\Services\MovementApprovalServices;
use App\Traits\PullOutHandler;
use App\Traits\ReusableFunctions\Reusables;
use Illuminate\Http\Request;

class PullOutController extends AssetMovementBaseController
{
    use PullOutHandler, Reusables;

    public function __construct(AssetPullOutServices $assetPullOutServices, MovementApprovalServices $movementApprovalServices)
    {
        parent::__construct(new PullOut(), $assetPullOutServices, $movementApprovalServices);
    }

    protected function movementCreateFormRequest()
    {
        return CreateAssetPullOutRequest::class;
    }

    protected function movementUpdateFormRequest()
    {
        return UpdateAssetPullOutRequest::class;
    }

    public function toPullOutViewing(UpdateAssetPullOutRequest $request)
    {

        $user = auth('sanctum')->user();
        $userRoleName = $user->role->role_name;
        $userSubUnit = $user->subunit_id;
        $isUserHMorME = SubUnit::where('id', $userSubUnit)
            ->whereIn('sub_unit_name', ['Hardware and Maintenance', 'Machinery and Equipment', 'Hardware & Maintenance', 'Machinery & Equipment'])
            ->exists();
        if ($this->isUserHMorME() && $isUserHMorME) {
            return $this->itemsToBeFullOutView($userRoleName);
        }
        return $this->responseUnprocessable('You are not allowed to view this page');
    }
}
