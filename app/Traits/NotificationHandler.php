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
            case 'getToApproveCount':
                $response['toApproveCount'] += $this->$function($user);
                break;
            case 'getToTagCount':
                $response['toTagCount'] += $this->$function($user);
                break;
            case 'getToRelease':
                $response['toRelease'] += $this->$function($user);
                break;
            case 'getToPurchaseRequest':
                $response['toPR'] += $this->$function($user);
                break;
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
        $fixeAssetCount = FixedAsset::where('from_request', 1)->where('print_count', 1)->where('can_release', 1)->where('is_released', 0)->count();
        $additionalCostCount = AdditionalCost::where('from_request', 1)->where('can_release', 1)->where('is_released', 0)->count();
        return $fixeAssetCount + $additionalCostCount;
    }

    private function getToPurchaseRequest($user)
    {
        return AssetRequest::where('status', 'Approved')->where('pr_number', null)->distinct('transaction_number')->count();
    }

    private function getToReceive($user)
    {
        return AssetRequest::where('status', 'Approved')->where('pr_number', '!=', null)
            ->whereRaw('quantity != quantity_delivered')->distinct('transaction_number')->count();
    }
}
