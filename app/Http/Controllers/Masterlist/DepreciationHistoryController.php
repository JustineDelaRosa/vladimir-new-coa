<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Models\DepreciationHistory;
use App\Models\FixedAsset;
use App\Models\Status\DepreciationStatus;
use App\Repositories\CalculationRepository;
use Carbon\Carbon;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class DepreciationHistoryController extends Controller
{
    use ApiResponse;

//    protected CalculationRepository $calculationRepository;

//    public function __construct(CalculationRepository $calculationRepository)
//    {
////        parent::__construct();
//        $this->calculationRepository = $calculationRepository;
//    }

    public function showHistory($vTagNumber)
    {
//        $test=[];
//        $runningDepreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
//        $fixed_assets = FixedAsset::with('formula')->where('depreciation_status_id', $runningDepreciationStatusId)->get();
//        //check which one is to be depreciated today based on start deprecation date
//        $selectedAsset = $fixed_assets->filter(function ($fixed_asset) {
//            $startDepreciationDate = $fixed_asset->formula->start_depreciation;
//            $endDepreciationDate = $fixed_asset->formula->end_depreciation;
//
//            if (is_string($startDepreciationDate)) {
//                $startDepreciationDate = Carbon::parse($startDepreciationDate);
//            }
//
//            if (is_string($endDepreciationDate)) {
//                $endDepreciationDate = Carbon::parse($endDepreciationDate);
//            }
//
//            if ($startDepreciationDate->equalTo($endDepreciationDate)) {
//                return $startDepreciationDate->isToday();
//            }
//
//            return $startDepreciationDate->lessThan(now()->addMonth())
//                && $startDepreciationDate->format('d') == now()->addMonth()->format('d')
//                && $endDepreciationDate->greaterThanOrEqualTo(now()->addMonth());
//
//        })->values();
//
//        foreach ($selectedAsset as $fixed_asset) {
//            $isOneTime = $fixed_asset->depreciation_method == "One Time";
//            //check if the asset has been depreciated before
//            $depreciationHistory = DepreciationHistory::where('fixed_asset_id', $fixed_asset->id)->latest()->first();
//            $agePerMonth = $this->calculationRepository->getMonthDifference($fixed_asset->formula->start_depreciation, now()->format('Y-m-d'));
//            $monthlyDepreciation = $this->calculationRepository->getMonthlyDepreciation($fixed_asset->acquisition_cost, $fixed_asset->formula->scrap_value, $fixed_asset->majorCategory->est_useful_life);
//            if (!$depreciationHistory) {
//                $accumulatedDepreciation = $this->calculationRepository->getAccumulatedCost($monthlyDepreciation, $agePerMonth, $fixed_asset->formula->depreciable_basis);
//            } else {
//                //sum all the accumulated depreciation of $depreciationHistory
//                $accumulatedDepreciation = $depreciationHistory->accumulated_depreciation + $monthlyDepreciation;
//            }
//
//            $test[]=[
//                'fixed_asset_id' => $fixed_asset->id,
//                'depreciated_date' => now()->addMonth(2)->format('Y-m-d'),
////                now()->addMonth(15)->format('Y-m-d')
//                'depreciated_amount_per_month' => $isOneTime ? $fixed_asset->acquisition_cost : $monthlyDepreciation,
//                'accumulated_depreciation' => $isOneTime ? $fixed_asset->acquisition_cost : $accumulatedDepreciation,
//                'book_value' => $isOneTime ? 0 : $fixed_asset->acquisition_cost - $accumulatedDepreciation,
//                'depreciation_basis' => $fixed_asset->formula->depreciable_basis
//            ];
//        }
//
//        return $test;


        $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)->first();
        $histories = DepreciationHistory::whereHas('fixedAsset', function ($query) use ($vTagNumber) {
            $query->where('vladimir_tag_number', $vTagNumber);
        })->orderBy('depreciated_date')->get();

        if ($histories->isEmpty() || !$fixedAsset) {
            return $this->responseUnprocessable('No depreciation history found for this asset');
        }

        $histories->each(function ($history) {
            $history->depreciated_date = Carbon::parse($history->depreciated_date);
        });

        // Transform the data to be returned
        $data = [
            'vladimir_tag_numbers' => $vTagNumber,
            'asset_description' => $fixedAsset->asset_description,
            'asset_specification' => $fixedAsset->asset_specification,
            'acquisition_cost' => $fixedAsset->acquisition_cost,
            'depreciation_history' => $histories->groupBy(function ($history) {
                return $history->depreciated_date->format('Y');
            })->sortKeysDesc()->map(function ($yearGroup, $year) {
                return [
                    'year' => $year,
                    'months' => $yearGroup->groupBy(function ($history) {
                        return $history->depreciated_date->format('F');
                    })->map(function ($monthGroup, $month) {
                        return [
                            'month' => ucwords($month),
                            'remaining_value' => $monthGroup->last()->remaining_book_value,
                            'monthly_depreciation' => $monthGroup->sum('depreciation_per_month'),
                            'accumulated_depreciation' => $monthGroup->sum('accumulated_cost'),
                            'company' => [
                                'company_code' => $monthGroup->first()->company->company_code,
                                'company_name' => $monthGroup->first()->company->company_name
                            ],
                            'business_unit' => [
                                'business_unit_code' => $monthGroup->first()->businessUnit->business_unit_code,
                                'business_unit_name' => $monthGroup->first()->businessUnit->business_unit_name
                            ],
                            'department' => [
                                'department_code' => $monthGroup->first()->department->department_code,
                                'department_name' => $monthGroup->first()->department->department_name
                            ],
                            'unit' => [
                                'unit_code' => $monthGroup->first()->unit->unit_code,
                                'unit_name' => $monthGroup->first()->unit->unit_name
                            ],
                            'sub_unit' => [
                                'sub_unit_code' => $monthGroup->first()->subUnit->sub_unit_code,
                                'sub_unit_name' => $monthGroup->first()->subUnit->sub_unit_name
                            ],
                            'location' => [
                                'location_code' => $monthGroup->first()->location->location_code,
                                'location_name' => $monthGroup->first()->location->location_name
                            ],

                        ];
                    })->values()
                ];
            })->values()
        ];
        return $data;
    }
}
