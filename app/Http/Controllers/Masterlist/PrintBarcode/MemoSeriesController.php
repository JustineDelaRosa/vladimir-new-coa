<?php

namespace App\Http\Controllers\Masterlist\PrintBarcode;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedAsset\MemorPrintRequest;
use App\Models\FixedAsset;
use App\Models\MemoSeries;
use Illuminate\Http\Request;

class MemoSeriesController extends Controller
{
    public function getMemoSeries()
    {
        //check first if there was a archived memo series
        $trashedMemoSeries = MemoSeries::onlyTrashed()->orderBy('id')->first();
        if ($trashedMemoSeries) {
            $trashedMemoSeries->restore();
            //update the memo series date to the current date
            $trashedMemoSeries->update(['memo_series' => now()->format('Ym') . '-' . str_pad($trashedMemoSeries->id, 4, '0', STR_PAD_LEFT)]);
            return response()->json([
                'id' => $trashedMemoSeries->id,
                'memo_series' => $trashedMemoSeries->memo_series
            ]);
        } else {
            return MemoSeries::generateMemoSeries();
        }
    }

    public function memoPrint(MemorPrintRequest $request)
    {
        $faIds = $request->input('fixed_asset_id', []);
        $departmentId = FixedAsset::where('vladimir_tag_number', $faIds[0])->first()->department_id;
        foreach ($faIds as $vTagNumber) {
            $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)->first();

            if ($fixedAsset->department_id != $departmentId) {
                return $this->responseUnprocessable('Fixed Assets are not in the same department');
            }
            if($fixedAsset->memo_series_id){
                return $this->responseUnprocessable('Fixed Asset already has a memo series');
            }
        }

        $memo = $this->getMemoSeries();
        foreach ($faIds as $vTagNumber) {
            $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)->first();

            $fixedAsset->update(['memo_series_id' => $memo->id]);
        }
        return $this->responseSuccess('Memo Printed Successfully', $memo);
    }

    public function memoReprint(){
        $reprintMemo = MemoSeries::with('fixedAssets')->useFilters()->dynamicPaginate();
        $reprintMemo->transform(function ($memo) {
            return [
                'id'=> $memo->id,
                'memo_series' => $memo->memo_series,
                'vladimir_tag_number' => $memo->fixedAssets->pluck('vladimir_tag_number')->toArray(),
                'created_at' => $memo->created_at,
            ];
//            $memo->fixedAssets->transform(function ($fixedAsset) {
//                return [
//                    'id' => $fixedAsset->id,
//                    'vladimir_tag_number' => $fixedAsset->vladimir_tag_number,
//                    'asset_description' => $fixedAsset->asset_description,
//                    'accountability' => $fixedAsset->accountability,
//                    'accountable' => $fixedAsset->accountable,
//                    'asset_specification' => $fixedAsset->asset_specification,
//                    'brand' => $fixedAsset->brand,
//                    'depreciation_method' => $fixedAsset->depreciation_method,
//                    'acquisition_cost' => $fixedAsset->acquisition_cost,
//                    'quantity' => $fixedAsset->quantity,
//                    'uom' =>[
//                        'id' => $fixedAsset->uom->id ?? '-',
//                        'uom_code' => $fixedAsset->uom->uom_code ?? '-',
//                        'uom_name' => $fixedAsset->uom->uom_name ?? '-',
//                    ],
//                    'supplier' => [
//                        'id' => $fixedAsset->supplier->id ?? '-',
//                        'supplier_code' => $fixedAsset->supplier->supplier_code ?? '-',
//                        'supplier_name' => $fixedAsset->supplier->supplier_name ?? '-',
//                    ],
//                ];
//            });
//            return $memo;
        });
        return $reprintMemo;
    }
}
