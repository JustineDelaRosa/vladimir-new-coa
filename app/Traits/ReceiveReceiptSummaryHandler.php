<?php

namespace App\Traits;

use App\Models\AdditionalCost;
use App\Models\AssetRequest;
use App\Models\FixedAsset;
use App\Models\TypeOfRequest;
use App\Models\YmirPRTransaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait ReceiveReceiptSummaryHandler
{
    public function rrNumberList($request)
    {
        $perPage = $request->get('per_page');
        $status = $request->get('status');

        $query = FixedAsset::with('formula')
//            ->where('is_released', 0)
            ->where('from_request', 1)
            ->whereNotNull('receipt')
            ->orderByDesc('rr_number');

        if($status == 'deactivated'){
            $query->onlyTrashed();
        }

        $fixedAssets = $query->useFilters()->get()->groupBy('receipt')->map(function ($fixed_asset) use ($status) {
            try {
                $YmirPRNumber = YmirPRTransaction::where('pr_number', $fixed_asset->first()->pr_number)->first()->pr_year_number_id ?? null;
            } catch (\Exception $e) {
                $YmirPRNumber = $fixed_asset->first()->pr_number;
            }
            return [
                'can_cancel' => $fixed_asset->map(function ($item) {
                    return is_null($item->formula->depreciation_method) && (is_null($item->formula->start_depreciation) || is_null($item->formula->end_depreciation)) ? 1 : 0;
                })->contains(0) ? 0 : 1,
                'transaction_number' => $fixed_asset->first()->transaction_number,
                'reference_number' => array_values($fixed_asset->pluck('reference_number')->unique()->all()),
                'ymir_pr_number'=>$YmirPRNumber ?: '-',
                'pr_number' => $fixed_asset->first()->pr_number,
                'rr_number' => $fixed_asset->first()->receipt,
                'po_number' => $fixed_asset->first()->po_number,
                'vladimir_tag_number' => $fixed_asset->pluck('vladimir_tag_number')->all(),
                'item_count' => $fixed_asset->count(),
                'remarks' => $status == 'deactivated' ? AssetRequest::where('pr_number', $fixed_asset->first()->pr_number)->first()->remarks ?? '-' : '-',
            ];
        })->values();

        if ($perPage !== null) {
            $page = $request->input('page', 1);
            $offset = $page * $perPage - $perPage;
            $fixedAssets = new LengthAwarePaginator($fixedAssets->slice($offset, $perPage)->values(), $fixedAssets->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        return $fixedAssets;
    }

    public function dataViewing($fixedAssetData)
    {
        if ($fixedAssetData instanceof LengthAwarePaginator) {
            $fixedAssetData->getCollection()->transform(function ($item) {
                return $this->collectionData($item);
            });
            return $fixedAssetData;
        } else if ($fixedAssetData instanceof Collection) {
            $fixedAssetData->transform(function ($item) {
                return $this->collectionData($item);
            });
            return $fixedAssetData;
        } else {
            return null;
        }


    }

    private function collectionData($fixedAssetData): array
    {
        return [
            'id' => $fixedAssetData->id,
            'transaction_number' => $fixedAssetData->transaction_number,
            'reference_number' => $fixedAssetData->reference_number,
            'pr_number' => $fixedAssetData->pr_number,
            'rr_number' => $fixedAssetData->receipt,
            'po_number' => $fixedAssetData->po_number,
            'vladimir_tag_number' => $fixedAssetData->vladimir_tag_number,
            'description' => $fixedAssetData->asset_description,
            'acquisition_cost' => $fixedAssetData->acquisition_cost,
            'inclusion' => $fixedAssetData->inclusion,
        ];
    }
}
