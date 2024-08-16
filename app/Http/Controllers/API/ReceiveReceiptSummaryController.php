<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Traits\ReceiveReceiptSummaryHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

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

    public function cancelledRR($rrNumber): \Illuminate\Http\JsonResponse
    {
        // Select all the fixed assets with the same rr number, including soft deleted ones
        $fixedAssets = FixedAsset::withTrashed()->where('receipt', $rrNumber)->get();

        // Check if any fixed asset is already soft deleted
        $isCancelled = $fixedAssets->contains(function ($fixedAsset) {
            return $fixedAsset->trashed();
        });

        if ($isCancelled) {
            return $this->responseUnprocessable('This RR Number is already cancelled');
        }

        // Cancel the fixed assets
        foreach ($fixedAssets as $fixedAsset) {
            $fixedAsset->delete(); // Soft delete
        }

        return $this->responseSuccess('RR Number has been successfully cancelled');
    }

}
