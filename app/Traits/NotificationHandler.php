<?php

namespace App\Traits;

use App\Models\AdditionalCost;
use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\FixedAsset;

trait NotificationHandler
{
    private function executeFunction($function, $user, $response)
    {
        switch ($function) {
            case 'getFaApproval':
            case 'getToApproveCount':
                $response['toApproveCount'] += $this->$function($user);
                break;
            case 'getToTagCount':
                $response['toTagCount'] += $this->$function($user);
                break;
            case 'getToRelease':
                $response['toRelease'] += $this->$function($user);
                break;
//            case 'getToPurchaseRequest':
//                $response['toPR'] += $this->$function($user);
//                break;
            case 'getToReceive':
                $response['toReceive'] += $this->$function($user);
                break;
        }
        return $response;
    }

    private function getToApproveCount($user)
    {
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        return AssetApproval::where('approver_id', $approverId)->where('status', 'For Approval')->count();
    }

    private function getToTagCount($user)
    {
        return FixedAsset::where('from_request', 1)->where('print_count', 0)->count();
    }

    private function getToRelease($user)
    {
        $fixeAssetCount = FixedAsset::where('from_request', 1)
            ->whereNotNull('print_count')
            ->where('can_release', 1)
            ->where('is_released', 0)
            ->whereHas('warehouse', function ($query) use ($user) {
                $query->where('location_id', $user->location_id);
            })
            ->where(function ($query) {
                $query->where('accountability', 'Common')
                    ->where('memo_series_id', null)
                    ->orWhere(function ($query) {
                        $query->where('accountability', 'Personal Issued')
                            ->where('asset_condition', '!=', 'New');
                    })->orWhere(function ($query) {
                        $query->where('accountability', 'Personal Issued')
                            ->where('asset_condition', 'New')
                            ->whereNotNull('memo_series_id');
                    });
            })->count();

        $additionalCostCount = AdditionalCost::where('from_request', 1)
            ->where('can_release', 1)
            ->where('is_released', 0)
            ->whereHas('warehouse', function ($query) use ($user) {
                $query->where('location_id', $user->location_id);
            })
            ->count();
        return $fixeAssetCount + $additionalCostCount;
    }

//    private function getToPurchaseRequest($user)
//    {
//        return AssetRequest::where('status', 'Approved')->where('pr_number', null)->distinct('transaction_number')->count();
//    }

    private function getToReceive($user)
    {
//        dd(AssetRequest::select('transaction_number')
//            ->where('status', 'Approved')
//            ->where('is_fa_approved', 1)
//            ->groupBy('transaction_number')
//            ->havingRaw('SUM(synced) >= 1')
//            ->havingRaw('SUM(quantity) != SUM(quantity_delivered)')
//            ->get()->toArray());

        return AssetRequest::select('transaction_number')
            ->where('status', 'Approved')
            ->where('is_fa_approved', 1)
            ->groupBy('transaction_number')
            ->havingRaw('SUM(synced) >= 1')
            ->havingRaw('SUM(quantity) != SUM(quantity_delivered)')
            ->count();
    }

    private function getFaApproval($user)
    {
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $assetApproval = AssetApproval::where('approver_id', $approverId)->where('status', 'For pAproval')->count();
        $forFaApproval = AssetRequest::where('status', 'Approved')->where('is_fa_approved', 0)->distinct('transaction_number')->count();
        return $assetApproval + $forFaApproval;
    }
}
