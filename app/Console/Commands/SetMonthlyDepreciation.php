<?php

namespace App\Console\Commands;

use App\Models\DepreciationHistory;
use App\Models\FixedAsset;
use App\Models\Status\DepreciationStatus;
use App\Repositories\CalculationRepository;
use App\Repositories\FixedAssetRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SetMonthlyDepreciation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'depreciation:set-monthly';
    protected $description = 'Set monthly depreciation for fixed assets with running depreciation status';
    protected CalculationRepository $calculationRepository;

    public function __construct(CalculationRepository $calculationRepository)
    {
        parent::__construct();
        $this->calculationRepository = $calculationRepository;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
//        $runningDepreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
//        ->where('depreciation_status_id', $runningDepreciationStatusId)
        $selectedAsset = FixedAsset::with(['formula', 'additionalCost'])
            ->get()
            ->map(function ($fixed_asset) {
                if ($fixed_asset->additionalCost) {
                    $addedUsefulLife = $fixed_asset->added_useful_life;
                    $fixed_asset->formula->end_depreciation = \Illuminate\Support\Carbon::parse($fixed_asset->formula->end_depreciation)->addYears($addedUsefulLife)->format('Y-m');
                }
                return $fixed_asset;
            })
            ->filter(function ($fixed_asset) {

                // Check if the fixed asset is fully depreciated
                $isFullyDepreciated = isset($fixed_asset->depreciationStatus) && $fixed_asset->depreciationStatus->depreciation_status_name === 'Fully Depreciated';

                // Check if all additional costs are fully depreciated
                $allAdditionalCostsFullyDepreciated = $fixed_asset->additionalCost->every(function ($additionalCost) {
                    return isset($additionalCost->depreciationStatus) && $additionalCost->depreciationStatus->depreciation_status_name === 'Fully Depreciated';
                });

                // Exclude the fixed asset if it and all its additional costs are fully depreciated
                if ($isFullyDepreciated && $allAdditionalCostsFullyDepreciated) {
                    return false;
                }

                $startDepreciationDate = Carbon::parse($fixed_asset->formula->start_depreciation);
                $endDepreciationDate = Carbon::parse($fixed_asset->formula->end_depreciation);
                if ($fixed_asset->added_useful_life) {
                    $endDepreciationDate->addYears($fixed_asset->added_useful_life);
                }

                if ($startDepreciationDate->equalTo($endDepreciationDate)) {
                    return $startDepreciationDate->isSameMonth(now());
                }

                return $startDepreciationDate->lessThanOrEqualTo(now()->startOfMonth())
                    && $endDepreciationDate->greaterThanOrEqualTo(now()->startOfMonth());
            })
            ->values();

//        $items = [];

        foreach ($selectedAsset as $fixed_asset) {
            $isOneTime = $fixed_asset->depreciation_method == "One Time";
            $additionalCost = $fixed_asset->additionalCost;
            $depreciationHistory = $fixed_asset->depreciationHistory;
            $formula = $fixed_asset->formula;
            $actualStartDepreciation = $formula->start_depreciation;

            $est_useful_life = $fixed_asset->majorCategory->est_useful_life ?? 0;
            $est_useful_life += $fixed_asset->added_useful_life;

            $end_depreciation = (date('Y-m', strtotime($formula->end_depreciation . ' + ' . ($fixed_asset->added_useful_life ?? 0) . ' years')));

            $goodAdditionalCost = $additionalCost->filter(function ($additionalCost) {
                return $additionalCost->assetStatus->asset_status_name !== 'Disposed';
            });


            $remainingBookValue = $depreciationHistory->last()->book_value ?? $formula->acquisition_cost;

            if ($fixed_asset->depreciationStatus->depreciation_status_name !== 'Fully Depreciated' && $fixed_asset->additionalCost->isEmpty()) {
                $remainingBookValue = $formula->acquisition_cost;
            }

            if ($fixed_asset->additionalCost->isNotEmpty()) {
                $formula->start_depreciation = Carbon::parse($fixed_asset->additionalCost->last()->created_at)->addMonth()->format('Y-m');
            }

            $goodAddCostTotal = $goodAdditionalCost->sum('acquisition_cost') ?? 0;
            $depreciationValue = $goodAddCostTotal + $remainingBookValue;
            $totalAcquisitionCost = $formula->acquisition_cost + $additionalCost->sum('acquisition_cost');

            $actualMonthsDepreciated = $this->calculationRepository->getMonthDifference($actualStartDepreciation, now()->format('Y-m')) ?? 0;
            $monthsDepreciated = $this->calculationRepository->getMonthDifference($formula->start_depreciation, now()->format('Y-m')) ?? 0;
            $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($depreciationValue, $formula->scrap_value, $est_useful_life) ?? 0;
            $yearly_depreciation = $this->calculationRepository->getYearlyDepreciation($depreciationValue, $formula->scrap_value, $est_useful_life) ?? 0;
            $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $monthsDepreciated, $depreciationValue) ?? 0;
            $remainingBookValue = $this->calculationRepository->getRemainingBookValue($depreciationValue, $accumulated_cost) ?? 0;

//            if($fixed_asset->depreciation_method == "One Time" && $additionalCost->count() > 0){
//                $fixed_asset->acquisition_cost = $fixed_asset->acquisition_cost + $additionalCost->sum('acquisition_cost');
//            }


            /*
             * todo dapat nag aacumulate and accumulated cost at remaining book value
             * example nag depreciate na ng 122 last month so may accumulated cost na sya ng 122,
             * dapat yung 122 na yun ang basehan ng depreciation this month, if mag depreciate na this month and computation is,
             * 122 last month + 122 this month = 244 na ang accumulated cost nya same sa remaining book value
             * todo: isDONE
             * */


            //check if the asset has been depreciated before
            /*            $depreciationHistory = DepreciationHistory::where('fixed_asset_id', $fixed_asset->id)->latest()->first();
                        if (!$depreciationHistory) {
                            $accumulatedDepreciation = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $monthsDepreciated, $depreciationValue);
                        } else {
                            //sum all the accumulated depreciation of $depreciationHistory
                            $accumulatedDepreciation = $depreciationHistory->accumulated_depreciation + $monthly_depreciation;
                        }*/

            $lastDepreciationHistory = DepreciationHistory::where('fixed_asset_id', $fixed_asset->id)->latest()->first();
            $previousAccumulatedCost = $lastDepreciationHistory ? $lastDepreciationHistory->accumulated_cost : 0;
            $previousRemainingBookValue = $lastDepreciationHistory ? $lastDepreciationHistory->remaining_book_value : $depreciationValue;
            $previousDepreciationBasis = $lastDepreciationHistory ? $lastDepreciationHistory->depreciation_basis : $depreciationValue;

            $currentAccumulatedCost = $previousAccumulatedCost + $monthly_depreciation;
            $currentRemainingBookValue = $previousRemainingBookValue - $monthly_depreciation;

            if ($previousDepreciationBasis < $depreciationValue) {
                $currentRemainingBookValue = $depreciationValue - $previousAccumulatedCost;
                $currentRemainingBookValue -= $monthly_depreciation;
            }


//            $yearsDepreciated = floor(($monthsDepreciated + $actualMonthsDepreciated) / 12);

            if ($fixed_asset->depreciation_method === "One Time" && $additionalCost->count() > 0) {
                $currentRemainingBookValue = $totalAcquisitionCost - $lastDepreciationHistory->accumulated_cost;

                if ($currentRemainingBookValue != 0) {
                    $currentRemainingBookValue = 0;
                    $depreciationValue = $additionalCost->sum('acquisition_cost');
                    $currentAccumulatedCost = $totalAcquisitionCost;
                }

                /*$items[] = [
                    'is_equal' => $isEqual,
                    'fixed_asset_id' => $fixed_asset->id,
                    'depreciated_date' => now()->format('Y-m'),
                    'estimated_useful_life' => $est_useful_life,
                    'remaining_useful_life' => $est_useful_life - ($monthsDepreciated + $actualMonthsDepreciated),
                    'months_depreciated' => $monthsDepreciated + $actualMonthsDepreciated,
                    'depreciation_per_month' => $depreciationValue,
                    'depreciation_per_year' => 0,
                    'accumulated_cost' => $currentAccumulatedCost,
                    'remaining_book_value' => $currentRemainingBookValue,
                    'depreciation_basis' => $depreciationValue,
                    'acquisition_cost' => $totalAcquisitionCost,
                ];*/
                DepreciationHistory::create([
                    'fixed_asset_id' => $fixed_asset->id,
                    'depreciated_date' => now()->addMonths()->format('Y-m'),
                    'depreciation_per_month' => $depreciationValue,
                    'months_depreciated' => $monthsDepreciated + $actualMonthsDepreciated,
                    'depreciation_per_year' => 0,
                    'accumulated_cost' => $currentAccumulatedCost,
                    'remaining_book_value' => $currentRemainingBookValue,
                    'depreciation_basis' => $depreciationValue,
                    'acquisition_cost' => $totalAcquisitionCost,
                    'company_id' => $fixed_asset->company_id,
                    'business_unit_id' => $fixed_asset->business_unit_id,
                    'department_id' => $fixed_asset->department_id,
                    'unit_id' => $fixed_asset->unit_id,
                    'subunit_id' => $fixed_asset->subunit_id,
                    'location_id' => $fixed_asset->location_id,
                    'account_id' => $fixed_asset->account_id,
                ]);

                //if the remaining book value is 0, then update the depreciation status to fully depreciated
                if ($currentRemainingBookValue == 0) {
                    $fixed_asset->update(['depreciation_status_id' => DepreciationStatus::where('depreciation_status_name', 'Fully Depreciated')->first()->id]);
                    // the the additional cost if there is one
                    if ($additionalCost->count() > 0) {
                        $additionalCost->each(function ($additionalCost) {
                            $additionalCost->update(['depreciation_status_id' => DepreciationStatus::where('depreciation_status_name', 'Fully Depreciated')->first()->id]);
                        });
                    }
                }

            } else {
                /*$items[] = [
                'totalAcq' => $previousAccumulatedCost,
                    'fixed_asset_id' => $fixed_asset->id,
                    'depreciated_date' => now()->format('Y-m'),
                    'estimated_useful_life' => $est_useful_life,
                    'remaining_useful_life' => $est_useful_life - $yearsDepreciated,
                    'months_depreciated' => $monthsDepreciated + $actualMonthsDepreciated,
                    'depreciation_per_month' => $monthly_depreciation,
                    'depreciation_per_year' => $yearly_depreciation,
                    'accumulated_cost' => $currentAccumulatedCost,
                    'remaining_book_value' => $currentRemainingBookValue,
                    'depreciation_basis' => $depreciationValue,
                    'acquisition_cost' => $totalAcquisitionCost,
                ];*/

                DepreciationHistory::create([
                    'fixed_asset_id' => $fixed_asset->id,
                    'depreciated_date' => now()->format('Y-m'),
                    'depreciation_per_month' => $monthly_depreciation,
                    'depreciation_per_year' => $yearly_depreciation,
                    'months_depreciated' => $monthsDepreciated + $actualMonthsDepreciated,
                    'accumulated_cost' => $currentAccumulatedCost,
                    'remaining_book_value' => $currentRemainingBookValue,
                    'depreciation_basis' => $depreciationValue,
                    'acquisition_cost' => $totalAcquisitionCost,
                    'company_id' => $fixed_asset->company_id,
                    'business_unit_id' => $fixed_asset->business_unit_id,
                    'department_id' => $fixed_asset->department_id,
                    'unit_id' => $fixed_asset->unit_id,
                    'subunit_id' => $fixed_asset->subunit_id,
                    'location_id' => $fixed_asset->location_id,
                    'account_id' => $fixed_asset->account_id,
                ]);

                //if the remaining book value is 0, then update the depreciation status to fully depreciated
                if ($currentRemainingBookValue == 0) {
                    $fixed_asset->update(['depreciation_status_id' => DepreciationStatus::where('depreciation_status_name', 'Fully Depreciated')->first()->id]);
                    // the the additional cost if there is one
                    if ($additionalCost->count() > 0) {
                        $additionalCost->each(function ($additionalCost) {
                            $additionalCost->update(['depreciation_status_id' => DepreciationStatus::where('depreciation_status_name', 'Fully Depreciated')->first()->id]);
                        });
                    }
                }
            }


        }

//        return $items;

        $this->info('Monthly depreciation set successfully.');
    }
}
