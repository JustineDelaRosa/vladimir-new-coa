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

        if (count($faIds) > 1) {
            $accountables = [];
            $accountabilities = [];

            foreach ($faIds as $vTagNumber) {
                $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)->first();
                $accountables[] = $fixedAsset->accountable;
                $accountabilities[] = $fixedAsset->accountability;
            }
            //check if all of the fixed assets accountability is Personal Issued
            if(!in_array("Personal Issued", $accountabilities)){
                return $this->responseUnprocessable('Fixed Asset accountability is Common');
            }

            if (count(array_unique($accountables)) > 1) {
                return $this->responseUnprocessable('Fixed Assets are not accountable to the same person');
            }
            if (count(array_unique($accountabilities)) > 1) {
                return $this->responseUnprocessable('Fixed Assets are not accountable by the same person');
            }
        }

        $departmentId = FixedAsset::where('vladimir_tag_number', $faIds[0])->first()->department_id;
        foreach ($faIds as $vTagNumber) {
            $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)->first();

            if($fixedAsset->accountability == "Common"){
                return $this->responseUnprocessable('Fixed Asset is accountable to Common');
            }

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

            if($fixedAsset->print_count > 0){
                $fixedAsset->update(['can_release' => 1]);
            }
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

    public function printData($id){
        $faData = FixedAsset::where('memo_series_id', $id)->get();

        return $faData->map(function ($fa) {
            return [
                'memo_series' => $fa->memoSeries->memo_series,
                'id' => $fa->id,
                'vladimir_tag_number' => $fa->vladimir_tag_number,
                'rr_number' => $fa->rr_number,
                'asset_description' => $fa->asset_description,
                'accountability' => $fa->accountability,
                'accountable' => $fa->accountable,
                'asset_specification' => $fa->asset_specification,
                'brand' => $fa->brand,
                'depreciation_method' => $fa->depreciation_method,
                'acquisition_cost' => $fa->acquisition_cost,
                'quantity' => $fa->quantity,
                'uom' =>[
                    'id' => $fa->uom->id ?? '-',
                    'uom_code' => $fa->uom->uom_code ?? '-',
                    'uom_name' => $fa->uom->uom_name ?? '-',
                ],
                'supplier' => [
                    'id' => $fa->supplier->id ?? '-',
                    'supplier_code' => $fa->supplier->supplier_code ?? '-',
                    'supplier_name' => $fa->supplier->supplier_name ?? '-',
                ],
                'company' => [
                    'id' => $fa->company->id ?? '-',
                    'company_code' => $fa->company->company_code ?? '-',
                    'company_name' => $fa->company->company_name ?? '-',
                ],
                'business_unit' => [
                    'id' => $fa->businessUnit->id ?? '-',
                    'business_unit_code' => $fa->businessUnit->business_unit_code ?? '-',
                    'business_unit_name' => $fa->businessUnit->business_unit_name ?? '-',
                ],
                'department' => [
                    'id' => $fa->department->id ?? '-',
                    'department_code' => $fa->department->department_code ?? '-',
                    'department_name' => $fa->department->department_name ?? '-',
                ],
                'unit' => [
                    'id' => $fa->unit->id ?? '-',
                    'unit_code' => $fa->unit->unit_code ?? '-',
                    'unit_name' => $fa->unit->unit_name ?? '-',
                ],
                'subunit' => [
                    'id' => $fa->subunit->id ?? '-',
                    'subunit_code' => $fa->subunit->subunit_code ?? '-',
                    'subunit_name' => $fa->subunit->subunit_name ?? '-',
                ],
                'location' => [
                    'id' => $fa->location->id ?? '-',
                    'location_code' => $fa->location->location_code ?? '-',
                    'location_name' => $fa->location->location_name ?? '-',
                ],
                'receiving_warehouse' => [
                    'id' => $fa->receivingWarehouse->id ?? '-',
                    'warehouse_code' => $fa->receivingWarehouse->warehouse_code ?? '-',
                    'warehouse_name' => $fa->receivingWarehouse->warehouse_name ?? '-',
                ],
            ];
        });
    }
}
