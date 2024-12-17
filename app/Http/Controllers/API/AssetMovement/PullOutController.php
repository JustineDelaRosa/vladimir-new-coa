<?php

namespace App\Http\Controllers\API\AssetMovement;

use App\Http\Controllers\AssetMovementBaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetPullOut\CreateAssetPullOutRequest;
use App\Http\Requests\AssetPullOut\EvaluationRequest;
use App\Http\Requests\AssetPullOut\UpdateAssetPullOutRequest;
use App\Models\Approvers;
use App\Models\AssetPullOutApprover;
use App\Models\MovementItemApproval;
use App\Models\PullOut;
use App\Models\RoleManagement;
use App\Models\SubUnit;
use App\Services\AssetPullOutServices;
use App\Services\MovementApprovalServices;
use App\Traits\PullOutHandler;
use App\Traits\ReusableFunctions\Reusables;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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

    public function toPullOutViewing(Request $request)
    {
        $user = auth('sanctum')->user();
        $userRoleName = $user->role->role_name;
        $userSubUnit = $user->subunit_id;
        $isUserHMorME = SubUnit::where('id', $userSubUnit)
            ->whereIn('sub_unit_name', ['Hardware and Maintenance', 'Machinery and Equipment', 'Hardware & Maintenance', 'Machinery & Equipment'])
            ->exists();
        if (($this->isUserHMorME() && $isUserHMorME) || $user->role->role_name == 'Admin') {
            return $this->itemsToBePullOutView($userRoleName, $request);
        }
        return $this->responseUnprocessable('You are not allowed to view this page');
    }

    public function toPullOutShow($movementId)
    {
        $user = auth('sanctum')->user();
        $userRoleName = $user->role->role_name;
        $userSubUnit = $user->subunit_id;
        $isUserHMorME = SubUnit::where('id', $userSubUnit)
            ->whereIn('sub_unit_name', ['Hardware and Maintenance', 'Machinery and Equipment', 'Hardware & Maintenance', 'Machinery & Equipment'])
            ->exists();
        if ($this->isUserHMorME() && $isUserHMorME) {
            return $this->itemsToBePullOutShow($userRoleName, $movementId);
        }
        return $this->responseUnprocessable('You are not allowed to view this page');
    }

    public function pickedUpConfirmation($movementId)
    {
        $user = auth('sanctum')->user();
        $userRoleName = $user->role->role_name;
        $userSubUnit = $user->subunit_id;
        $isUserHMorME = SubUnit::where('id', $userSubUnit)
            ->whereIn('sub_unit_name', ['Hardware and Maintenance', 'Machinery and Equipment', 'Hardware & Maintenance', 'Machinery & Equipment'])
            ->exists();
        if ($this->isUserHMorME() && $isUserHMorME) {
            return $this->pickedUpConfirmationView($userRoleName, $movementId);
        }
        return $this->responseUnprocessable('You are not allowed to view this page');
    }

    public function listOfItemsToEvaluate(Request $request)
    {
        $user = auth('sanctum')->user();
        $userRoleName = $user->role->role_name;
        $userSubUnit = $user->subunit_id;

        $isUserHMorME = SubUnit::where('id', $userSubUnit)
            ->whereIn('sub_unit_name', ['Hardware and Maintenance', 'Machinery and Equipment', 'Hardware & Maintenance', 'Machinery & Equipment'])
            ->exists();
        if (($this->isUserHMorME() && $isUserHMorME) || $user->role->role_name == 'Admin') {
            return $this->itemsToEvaluate($userRoleName, $request);
        }
        return $this->responseUnprocessable('You are not allowed to view this page');
    }

    public function evaluateItems(EvaluationRequest $request)
    {

        try {
            DB::beginTransaction();
            $user = auth('sanctum')->user();
            $userRoleName = $user->role->role_name;
            $userSubUnit = $user->subunit_id;
            $isUserHMorME = SubUnit::where('id', $userSubUnit)
                ->whereIn('sub_unit_name', ['Hardware and Maintenance', 'Machinery and Equipment', 'Hardware & Maintenance', 'Machinery & Equipment'])
                ->exists();
            if ($this->isUserHMorME() && $isUserHMorME) {
                $this->evaluateItem($request, $userRoleName);
                DB::commit();
                return $this->responseSuccess('Items evaluated successfully');
            }
            return $this->responseUnprocessable('You are not allowed to view this page');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->responseServerError($exception->getMessage());
        }
    }

    public function itemApprovalView(Request $request)
    {

        $forMonitoring = $request->for_monitoring ?? false;
        $perPage = $request->input('per_page', null);
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->pluck('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->pluck('id');
        $status = $request->input('status', 'For Approval');

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        $movementItemQuery = MovementItemApproval::query();

        if (!$forMonitoring) {
            $movementItemQuery->where('approver_id', $approverId);
        }
        $pullOut = [];
        if ($this->isUserFa()) {
            $pullOut = PullOut::where('status', 'Approved')
                ->when($status == 'Approved', function ($query) {
                    return $query->where('is_fa_approved', true);
                }, function ($query) {
                    return $query->where('is_fa_approved', false);
                })
                ->pluck('id');
        }

        $movementItem = $movementItemQuery->where('status', $status)->get();
        $pullOut = is_array($pullOut) ? $pullOut : [$pullOut];
        $pullOut = array_merge($pullOut, $movementItem->pluck('item_id')->toArray());
        $pullOut = Arr::flatten($pullOut);

        $data = PullOut::whereIn('id', $pullOut)
            ->when($status == 'For Approval', function ($query) {
                return $query->orderBy(function ($query) {
                    return $this->getOrderByQuery($query);
                });
            }, function ($query) {
                return $query->orderBy(function ($query) {
                    return $this->getOrderByQuery($query);
                }, 'desc');
            })
            ->get();
        return $this->itemApprovalViewing($data, $request);
    }

    private function getOrderByQuery($query)
    {
        return $query->select('created_at')
            ->from('activity_log')
            ->whereColumn('subject_id', 'pullouts.id')
            ->orderBy('created_at', 'desc')
            ->limit(1);
    }

    public function handleItemApproval(Request $request)
    {
        $userId = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $userId)->first()->id ?? '';
        if (!$approverId) {
            return $this->responseUnprocessable('Invalid User');
        }
        $action = $request->input('action');
        $itemIds = $request->input('item_ids');

        $pullout = PullOut::whereIn('id', $itemIds)->get();
        if ($pullout->isEmpty()) {
            return $this->responseUnprocessable('Invalid Item');
        }

        switch (strtolower($action)) {
            case 'approve':
                $this->approveItem($itemIds, $approverId);
                break;
            case 'reject':
                $this->rejectItem($pullout, $approverId);
                break;
            default:
                return $this->responseUnprocessable('Invalid Action');
        }

    }

    //Todo: pending
    private function approveItem($itemIds, $approverId)
    {
        foreach ($itemIds as $itemId) {
            $pullOutItemApproval = MovementItemApproval::where('item_id', $itemId)
                ->where('approver_id', $approverId)
                ->get();
        }

    }
}
