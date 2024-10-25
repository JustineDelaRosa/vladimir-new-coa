<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\FixedAsset;
use App\Traits\ReceiveReceiptSummaryHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiveReceiptSummaryController extends Controller
{
    use ApiResponse, ReceiveReceiptSummaryHandler;

    public function index(Request $request)
    {
        return $this->rrNumberList($request);
    }

    public function show($rrNumber)
    {
        $fixedAssets = FixedAsset::where('receipt', $rrNumber)->dynamicPaginate();

        return $this->dataViewing($fixedAssets);
    }

    public function cancelledRR(Request $request, $rrNumber)
    {
        $remarks = $request->get('reason');
        // Select all the fixed assets with the same rr number, including soft deleted ones
        $fixedAssets = FixedAsset::withTrashed()->where('receipt', $rrNumber)->get();
        if ($fixedAssets->isEmpty()) {
            return $this->responseUnprocessable('RR Number not found');
        }

        // Check if any fixed asset is already soft deleted
//        $isCancelled = $fixedAssets->contains(function ($fixedAsset) {
//            return $fixedAsset->trashed();
//        });
//
//        if ($isCancelled) {
//            return $this->responseUnprocessable('This RR Number is already cancelled');
//        }

        //get the count of all the fixed assets with the same rr number and reference number

        $assetRequestProcess = $this->removeCancelledRR($fixedAssets->pluck('reference_number')->toArray(), $rrNumber, $remarks);
        if (!$assetRequestProcess) {
            return $this->responseUnprocessable('Error cancelling RR Number');
        }

        // Cancel the fixed assets
        foreach ($fixedAssets as $fixedAsset) {
            $fixedAsset->delete(); // Soft delete
        }

        return $this->responseSuccess('RR Number has been successfully cancelled');
    }

    public function removeCancelledRR($referenceNumbers, $rrNumber, $remarks)
    {
        DB::beginTransaction();
        try {
            $uniqueReferenceNumbers = array_unique($referenceNumbers);
            foreach ($uniqueReferenceNumbers as $reference) {
                $itemCount = FixedAsset::where('reference_number', $reference)->where('receipt', $rrNumber)->count();
                $assetRequest = AssetRequest::where('reference_number', $reference)->first();

                if ($assetRequest) {
                    $rrNumbers = explode(',', $assetRequest->rr_number);
                    $rrNumbers = array_diff($rrNumbers, [$rrNumber]);
                    $rrNumbers = implode(',', $rrNumbers);

                    if (empty($rrNumbers)) {
                        $assetRequest->update([
                            'rr_number' => null, // or use an empty string if preferred
                            'filter' => 'Sent to Ymir',
                            'remarks' => $remarks,
                            'synced' => 0,
                            'quantity_delivered' => 0
                        ]);
                    } else {
                        $assetRequest->update([
                            'rr_number' => $rrNumbers,
                            'filter' => 'Sent to Ymir',
                            'remarks' => $remarks,
                            'quantity_delivered' => $assetRequest->quantity_delivered - $itemCount
                        ]);
                    }
                }
            }
            DB::commit();
            $this->cancelledLog($uniqueReferenceNumbers, $remarks);
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function cancelledLog(array $uniqueReferenceNumbers, $remarks)
    {
        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        foreach ($uniqueReferenceNumbers as $reference) {
            activity()
                ->causedBy($user)
                ->performedOn($assetRequests)
                ->withProperties([
                    'reference_number' => $reference,
                    'remarks' => $remarks
                    ])
                ->inLog('RR number cancelled.')
                ->tap(function ($activity) use ($user, $reference) {
                    AssetRequest::where('reference_number', $reference)->get()->each(function ($assetRequest) use ($activity) {
                        $activity->subject_id = $assetRequest->transaction_number;
                    });
                })
                ->log('RR number cancelled.');
        }
//$this->composeLogPropertiesPo($assetRequest, $poNumber, $rrNumber, $removedCount, $removeRemaining, $remove)
    }


}
