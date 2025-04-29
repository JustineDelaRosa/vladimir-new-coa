<?php

namespace App\Traits;

use App\Models\AdditionalCost;
use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\AssetSmallTool;
use App\Models\FixedAsset;
use App\Models\MovementApproval;
use App\Models\MovementNumber;
use App\Models\Transfer;

trait NotificationHandler
{
    public function executeFunction($function, $user, $response)
    {
        switch ($function) {
            case 'getAcquisitionFaApproval':
                $response['toAcquisitionFaApproval'] += $this->$function($user);
                break;
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
            case 'getTransferFaApproval':
                $response['toTransferFaApproval'] += $this->$function($user);
                break;
            case 'getToTransfer':
                $response['toTransferApproveCount'] += $this->$function($user);
                break;
            case 'getToTransferReceiving':
                $response['toTransferReceiving'] += $this->$function($user);
                break;
            case 'getToReceive':
                $response['toReceive'] += $this->$function($user);
                break;
            case 'getToSmallToolTagging':
                $response['toSmallToolTagging'] += $this->$function($user);
                break;

        }
        return $response;
    }

    private function getToApproveCount($user)
    {
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $assetApproval = AssetApproval::where('approver_id', $approverId)->where('status', 'For Approval')->count();
        return $assetApproval;
    }

    private function getToTagCount($user)
    {
        return FixedAsset::where('from_request', 1)->where('print_count', 0)
            ->where('is_printable', 1)
            ->whereHas('typeOfRequest', function ($query) {
                $query->whereNotIn('type_of_request_name', ['Small Tools', 'Small Tool']);
            })
            ->count();
    }

    private function getToRelease($user)
    {
        $userWarehouseId = auth('sanctum')->user()->warehouse_id;
        $fixeAssetCount = FixedAsset::where('from_request', 1)
            ->whereNotNull('print_count')
            ->where('can_release', 1)
            ->where('is_released', 0)
            ->where('warehouse_id', $userWarehouseId)
            ->where(function ($query) {
                $query->where('accountability', 'Common')
                    ->where('memo_series_id', null)
                    ->orWhere(function ($query) {
                        $query->where('accountability', 'Personal Issued');
//                            ->where('asset_condition', '!=', 'New');
                    })->orWhere(function ($query) {
                        $query->where('accountability', 'Personal Issued')
//                            ->where('asset_condition', 'New')
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
            ->where('receiving_warehouse_id', $user->warehouse_id)
            ->groupBy('transaction_number')
            ->havingRaw('SUM(synced) >= 1')
            ->havingRaw('SUM(quantity) != SUM(quantity_delivered)')
            ->count();
    }

    private function getAcquisitionFaApproval($user)
    {
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $assetApproval = AssetApproval::where('approver_id', $approverId)->where('status', 'For Approval')->count();
        $forFaApproval = AssetRequest::where('status', 'Approved')->where('is_fa_approved', 0)->distinct('transaction_number')->count();
        return $forFaApproval;
    }

    private function getTransferFaApproval($user)
    {
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $assetApproval = MovementApproval::where('approver_id', $approverId)->where('status', 'For Approval')->count();
        $movementFaApproval = MovementNumber::where('status', 'Approved')->where('is_fa_approved', 0)->distinct('id')->count();
        return $movementFaApproval;
    }

    private function getToTransfer($user)
    {
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $transferApproval = MovementApproval::whereHas('movementNumber.transfer')
            ->where('approver_id', $approverId)->where('status', 'For Approval')->count();
        return $transferApproval;
    }

    private function getToTransferReceiving($user)
    {
        /*$toReceived = MovementNumber::where('status', 'Approved')->where('is_fa_approved', 1)
            ->where('is_received', 0)
            ->whereHas('transfer', function ($query) use ($user) {
                $query->where('received_at', null)->count();
            })->count();*/

        $toReceived = Transfer::whereHas('movementNumber', function ($query) use ($user) {
            $query->where('status', 'Approved')->where('is_fa_approved', 1)
                ->where('is_received', 0);
        })->where('receiver_id', $user->id)->where('received_at', null)->count();


//        return 100;
        return $toReceived;
    }

    private function getToSmallToolTagging($user)
    {
        /*        $assetSmallTools = AssetSmallTool::where('to_release', 1)
                    ->where('receiving_warehouse_id', $user->warehouse_id)
                    ->count();

                return $assetSmallTools;*/

        return FixedAsset::where('from_request', 1)->where('print_count', 0)
            ->where('can_release', 0)
            ->whereHas('typeOfRequest', function ($query) {
                $query->whereIn('type_of_request_name', ['Small Tools', 'Small Tool']);
            })->count();
    }
}
