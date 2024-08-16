<?php

namespace App\Traits;

use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Models\TypeOfRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait ReceiveReceiptSummaryHandler
{
    public function rrNumberList($request)
    {
        $perPage = $request->get('per_page');

        $query = FixedAsset::where('can_release', 1)
            ->where('from_request', 1)
            ->whereNotNull('receipt')
            ->orderByDesc('created_at');

        $fixedAssets = $query->useFilters()->get()->groupBy('receipt')->map(function ($fixed_asset) {
            return [
                'transaction_number' => $fixed_asset->pluck('transaction_number')->all(),
                'reference_number' => $fixed_asset->pluck('reference_number')->all(),
                'pr_number' => $fixed_asset->first()->pr_number,
                'rr_number' => $fixed_asset->first()->receipt,
                'po_number' => $fixed_asset->first()->po_number,
                'vladimir_tag_number' => $fixed_asset->pluck('vladimir_tag_number')->all(),
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
                return $this->notCollectionData($item);
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
            'inclusion' => $fixedAssetData->inclusion,
        ];
    }
    private function notCollectionData($fixedAssetData): array
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
            'inclusion' => $fixedAssetData->inclusion,
        ];
    }
}
