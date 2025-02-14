<?php

namespace App\Console\Commands;

use App\Models\FixedAsset;
use App\Models\Status\DepreciationStatus;
use App\Repositories\CalculationRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DepreciationHistory extends Command
{

    protected $signature = 'depreciation:monthly-depreciation';
    protected $description = 'Set monthly depreciation for fixed assets with running depreciation status';
    protected CalculationRepository $calculationRepository;

    public function __construct(CalculationRepository $calculationRepository)
    {
        parent::__construct();
        $this->calculationRepository = $calculationRepository;
    }

    public function handle()
    {
        try {

            FixedAsset::with([
                'formula',
                'additionalCost.depreciationStatus',
                'depreciationStatus',
                'majorCategory',
                'depreciationHistory'
            ])->where('vladimir_tag_number', 5250210478396)
                ->chunk(200, function ($assets) {
                    $selectedAssets = $assets->map(function ($fixedAsset) {
                        // Adjust end_depreciation if there are additional costs
                        if ($fixedAsset->additionalCost->isNotEmpty()) {
                            $addedUsefulLifeYears = $fixedAsset->added_useful_life ?? 0;
                            $addedUsefulLifeMonths = $addedUsefulLifeYears * 12;
                            $fixedAsset->formula->end_depreciation = Carbon::parse($fixedAsset->formula->end_depreciation)
                                ->addMonths($addedUsefulLifeMonths)
                                ->format('Y-m');
                        }
                        return $fixedAsset;
                    })->filter(function ($fixedAsset) {
                        // Exclude fully depreciated assets and their costs
                        $isFullyDepreciated = $fixedAsset->depreciationStatus->depreciation_status_name === 'Fully Depreciated';
                        $allCostsDepreciated = $fixedAsset->additionalCost->every(function ($cost) {
                            return $cost->depreciationStatus->depreciation_status_name === 'Fully Depreciated';
                        });

                        if ($isFullyDepreciated && $allCostsDepreciated) return false;

                        // Date comparisons
                        $start = Carbon::parse($fixedAsset->formula->start_depreciation)->startOfMonth();
                        $end = Carbon::parse($fixedAsset->formula->end_depreciation)->startOfMonth();
                        $now = now()->startOfMonth();

                        return $start <= $now && $end >= $now;
                    });

                    foreach ($selectedAssets as $asset) {
                        $this->processAssetDepreciation($asset);
                    }
                });
            $this->info("Monthly depreciation set successfully: date: " . now()->format('Y-m'));
        } catch (\Exception $e) {
            Log::error('Error setting monthly depreciation: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile());
            $this->error('Error setting monthly depreciation: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile());
            $this->error('An error occurred while setting monthly depreciation.');
        }
    }

    protected function processAssetDepreciation($fixedAsset)
    {
        $formula = $fixedAsset->formula;
        $lastHistory = $fixedAsset->depreciationHistory->sortByDesc('depreciated_date')->first();

        $startDate = Carbon::parse($formula->start_depreciation);
        $currentDate = now()->startOfMonth();

        // Original depreciation parameters
        $originalBasis = $formula->depreciable_basis;
        $scrapValue = $formula->scrap_value;
        $originalLifeMonths = $fixedAsset->majorCategory->est_useful_life * 12;
        $originalMonthlyDepr = max(round(($originalBasis - $scrapValue) / $originalLifeMonths, 2), 0);

        // Additional costs
        $additionalCosts = $fixedAsset->additionalCost;
        $totalAddedLife = $fixedAsset->sum('added_useful_life');
        $totalAdditionalBasis = round($additionalCosts->sum(function ($cost) {
            return $cost->formula->depreciable_basis;
        }), 2);

        // Determine effective start date and months depreciated
        $lastCostDate = $additionalCosts->isNotEmpty()
            ? Carbon::parse($additionalCosts->last()->created_at)->addMonth()->startOfMonth()
            : null;

        $monthsDeprOriginal = 0;
        $accumulatedDepr = 0;

        if ($lastCostDate) {
            // Months from asset start to last additional cost
            $monthsDeprOriginal = $startDate->diffInMonths($lastCostDate);
            $monthsDeprOriginal = max($monthsDeprOriginal, 0); // Ensure non-negative
            $accumulatedDepr = round($originalMonthlyDepr * $monthsDeprOriginal, 2);

            // Remaining book value after depreciation up to last additional cost
            $remainingBookValue = max(round($originalBasis - $accumulatedDepr, 2), 0);

            // New basis and remaining life
            $totalBasis = round($remainingBookValue + $totalAdditionalBasis, 2);
            $remainingLife = ($originalLifeMonths + $totalAddedLife) - $monthsDeprOriginal;
            $remainingLife = max($remainingLife, 1); // Prevent division by zero

            // Calculate new monthly depreciation from last additional cost date
            $monthlyDepr = max(round(($totalBasis - $scrapValue) / $remainingLife, 2), 0);

            // Months from last additional cost to current date
            $monthsAfterAddition = $lastCostDate->diffInMonths($currentDate);
            $monthsAfterAddition = $currentDate >= $lastCostDate ? $monthsAfterAddition : 0;

            // Total accumulated depreciation
            $accumulatedDepr += round($monthlyDepr * $monthsAfterAddition, 2);
            $totalMonthsDepr = $monthsDeprOriginal + $monthsAfterAddition;
        } else {
            // No additional costs: simple depreciation
            $totalBasis = $originalBasis;
            $monthlyDepr = $originalMonthlyDepr;
            $totalMonthsDepr = $startDate->diffInMonths($currentDate);
            $totalMonthsDepr = max($totalMonthsDepr, 0);
            $accumulatedDepr = round($monthlyDepr * $totalMonthsDepr, 2);
        }

        // Adjust for existing history
        if ($lastHistory) {
            // If there's existing history, adjust accumulated depreciation
            $accumulatedDepr = round($lastHistory->accumulated_cost + $monthlyDepr, 2);
            $totalMonthsDepr = $lastHistory->months_depreciated + 1;
        }

        // Ensure accumulated depreciation doesn't exceed depreciable basis
        $maxAccumulated = max(round($totalBasis - $scrapValue, 2), 0);
        $accumulatedDepr = min($accumulatedDepr, $maxAccumulated);
        $remainingValue = max(round($totalBasis - $accumulatedDepr, 2), 0);

        // Check if it got transferred to other COA
        $company_id = $fixedAsset->company_id;
        $business_unit_id = $fixedAsset->business_unit_id;
        $department_id = $fixedAsset->department_id;
        $unit_id = $fixedAsset->unit_id;
        $subunit_id = $fixedAsset->subunit_id;
        $location_id = $fixedAsset->location_id;
        $depreciation_debit_id = $fixedAsset->accountingEntries->depreciationDebit->sync_id;
        $isTransferred = 0;

        if ($lastHistory && $lastHistory->is_transferred == 0) {
            $company_id = $lastHistory->company_id == $company_id ? $company_id : $lastHistory->company_id;
            $business_unit_id = $lastHistory->business_unit_id == $business_unit_id ? $business_unit_id : $lastHistory->business_unit_id;
            $department_id = $lastHistory->department_id == $department_id ? $department_id : $lastHistory->department_id;
            $unit_id = $lastHistory->unit_id == $unit_id ? $unit_id : $lastHistory->unit_id;
            $subunit_id = $lastHistory->subunit_id == $subunit_id ? $subunit_id : $lastHistory->subunit_id;
            $location_id = $lastHistory->location_id == $location_id ? $location_id : $lastHistory->location_id;
            $depreciation_debit_id = $lastHistory->depreciation_debit_id == $depreciation_debit_id ? $depreciation_debit_id : $lastHistory->depreciation_debit_id;
            $isTransferred = $lastHistory->subunit_id == $subunit_id ? 0 : 1;
        }

        // Create or update depreciation record
        \App\Models\DepreciationHistory::create([
            'fixed_asset_id' => $fixedAsset->id,
            'depreciated_date' => $currentDate->format('Y-m'),
            'depreciation_per_month' => round($monthlyDepr, 2),
            'depreciation_per_year' => round($monthlyDepr * 12, 2),
            'months_depreciated' => $remainingLife,
            'accumulated_cost' => round($accumulatedDepr, 2),
            'remaining_book_value' => round($remainingValue, 2),
            'depreciation_basis' => round($totalBasis, 2),
            'acquisition_cost' => round($formula->acquisition_cost + $additionalCosts->sum('acquisition_cost'), 2),
            'company_id' => $company_id,
            'business_unit_id' => $business_unit_id,
            'department_id' => $department_id,
            'unit_id' => $unit_id,
            'subunit_id' => $subunit_id,
            'location_id' => $location_id,
            'depreciation_debit_id' => $depreciation_debit_id,
            'is_transferred' => $isTransferred,
        ]);

        // Update status if fully depreciated
        if ($remainingValue <= 0) {
            $statusId = DepreciationStatus::firstWhere('depreciation_status_name', 'Fully Depreciated')->id;
            $fixedAsset->update(['depreciation_status_id' => $statusId]);
            $fixedAsset->additionalCost->each->update(['depreciation_status_id' => $statusId]);
        }
    }


}
/*    protected function processAssetDepreciation($fixedAsset)
    {
        $formula = $fixedAsset->formula;
        $lastHistory = $fixedAsset->depreciationHistory->sortByDesc('depreciated_date')->first();

        $startDate = Carbon::parse($formula->start_depreciation);
        $currentDate = now()->startOfMonth();

        // Original depreciation parameters
        $originalBasis = $formula->depreciable_basis;
        $scrapValue = $formula->scrap_value;
        $originalLifeMonths = $fixedAsset->majorCategory->est_useful_life * 12;
        $originalMonthlyDepr = max(($originalBasis - $scrapValue) / $originalLifeMonths, 0);

        // Additional costs
        $additionalCosts = $fixedAsset->additionalCost;
        $totalAddedLife = $fixedAsset->sum('added_useful_life');
        $totalAdditionalBasis = $additionalCosts->sum(function ($cost) {
            return $cost->formula->depreciable_basis;
        });

        // Determine effective start date and months depreciated
        $lastCostDate = $additionalCosts->isNotEmpty()
            ? Carbon::parse($additionalCosts->last()->created_at)->addMonth()->startOfMonth()
            : null;

        $monthsDeprOriginal = 0;
        $accumulatedDepr = 0;

        if ($lastCostDate) {
            // Months from asset start to last additional cost
            $monthsDeprOriginal = $startDate->diffInMonths($lastCostDate);
            $monthsDeprOriginal = max($monthsDeprOriginal, 0); // Ensure non-negative
            $accumulatedDepr = $originalMonthlyDepr * $monthsDeprOriginal;

            // Remaining book value after depreciation up to last additional cost
            $remainingBookValue = max($originalBasis - $accumulatedDepr, 0);

            // New basis and remaining life
            $totalBasis = $remainingBookValue + $totalAdditionalBasis;
            $remainingLife = ($originalLifeMonths + $totalAddedLife) - $monthsDeprOriginal;
            $remainingLife = max($remainingLife, 1); // Prevent division by zero

            // Calculate new monthly depreciation from last additional cost date
            $monthlyDepr = max(($totalBasis - $scrapValue) / $remainingLife, 0);

            // Months from last additional cost to current date
            $monthsAfterAddition = $lastCostDate->diffInMonths($currentDate);
            $monthsAfterAddition = $currentDate >= $lastCostDate ? $monthsAfterAddition : 0;

            // Total accumulated depreciation
            $accumulatedDepr += $monthlyDepr * $monthsAfterAddition;
            $totalMonthsDepr = $monthsDeprOriginal + $monthsAfterAddition;
        } else {
            // No additional costs: simple depreciation
            $totalBasis = $originalBasis;
            $monthlyDepr = $originalMonthlyDepr;
            $totalMonthsDepr = $startDate->diffInMonths($currentDate);
            $totalMonthsDepr = max($totalMonthsDepr, 0);
            $accumulatedDepr = $monthlyDepr * $totalMonthsDepr;
        }

        // Adjust for existing history
        if ($lastHistory) {
            // If there's existing history, adjust accumulated depreciation
            $accumulatedDepr = $lastHistory->accumulated_cost + $monthlyDepr;
            $totalMonthsDepr = $lastHistory->months_depreciated + 1;
        }

        // Ensure accumulated depreciation doesn't exceed depreciable basis
        $maxAccumulated = max($totalBasis - $scrapValue, 0);
        $accumulatedDepr = min($accumulatedDepr, $maxAccumulated);
        $remainingValue = max($totalBasis - $accumulatedDepr, 0);



        //check if it got transferred to other COA
        $company_id = $fixedAsset->company_id;
        $business_unit_id = $fixedAsset->business_unit_id;
        $department_id = $fixedAsset->department_id;
        $unit_id = $fixedAsset->unit_id;
        $subunit_id = $fixedAsset->subunit_id;
        $location_id = $fixedAsset->location_id;
        $depreciation_debit_id = $fixedAsset->accountingEntries->depreciationDebit->sync_id;
//            $fixedAsset->accountingEntries->depreicaionDebit->sync_id;

        if($lastHistory && $lastHistory->is_transferred == 0){
            $company_id = $lastHistory->company_id == $company_id ? $company_id : $lastHistory->company_id;
            $business_unit_id = $lastHistory->business_unit_id == $business_unit_id ? $business_unit_id : $lastHistory->business_unit_id;
            $department_id = $lastHistory->department_id == $department_id ? $department_id : $lastHistory->department_id;
            $unit_id = $lastHistory->unit_id == $unit_id ? $unit_id : $lastHistory->unit_id;
            $subunit_id = $lastHistory->subunit_id == $subunit_id ? $subunit_id : $lastHistory->subunit_id;
            $location_id = $lastHistory->location_id == $location_id ? $location_id : $lastHistory->location_id;
            $depreciation_debit_id = $lastHistory->depreciation_debit_id == $depreciation_debit_id ? $depreciation_debit_id : $lastHistory->depreciation_debit_id;
        }





        // Create or update depreciation record
        \App\Models\DepreciationHistory::Create(
            [
                'fixed_asset_id' => $fixedAsset->id,
                'depreciated_date' => $currentDate->format('Y-m'),
                'depreciation_per_month' => round($monthlyDepr, 2),
                'depreciation_per_year' => round($monthlyDepr * 12, 2),
                'months_depreciated' => $remainingLife,
                'accumulated_cost' => round($accumulatedDepr, 2),
                'remaining_book_value' => round($remainingValue, 2),
                'depreciation_basis' => round($totalBasis, 2),
                'acquisition_cost' => round($formula->acquisition_cost + $additionalCosts->sum('acquisition_cost'), 2),
                'company_id' => $company_id,
                'business_unit_id' => $business_unit_id,
                'department_id' => $department_id,
                'unit_id' => $unit_id,
                'subunit_id' => $subunit_id,
                'location_id' => $location_id,
                'depreciation_debit_id' => $depreciation_debit_id,
                'is_transferred' => false,
            ]
        );

        // Update status if fully depreciated
        if ($remainingValue <= 0) {
            $statusId = DepreciationStatus::firstWhere('depreciation_status_name', 'Fully Depreciated')->id;
            $fixedAsset->update(['depreciation_status_id' => $statusId]);
            $fixedAsset->additionalCost->each->update(['depreciation_status_id' => $statusId]);
        }
    }*/

/*\App\Models\DepreciationHistory::create([
    'fixed_asset_id' => $fixedAsset->id,
    'depreciated_date' => now()->format('Y-m'),
    'depreciation_per_month' => $monthlyDepreciation,
    'depreciation_per_year' => $monthlyDepreciation * 12,
    'months_depreciated' => ($lastHistory->months_depreciated ?? 0) + 1,
    'accumulated_cost' => $accumulatedCost,
    'remaining_book_value' => $remainingValue,
    'depreciation_basis' => $totalDepreciationBasis,
    'acquisition_cost' => $formula->acquisition_cost + $fixedAsset->additionalCost->sum('acquisition_cost'),
    'company_id' => $fixedAsset->company_id,
    'business_unit_id' => $fixedAsset->business_unit_id,
    'department_id' => $fixedAsset->department_id,
    'unit_id' => $fixedAsset->unit_id,
    'subunit_id' => $fixedAsset->subunit_id,
    'location_id' => $fixedAsset->location_id,
    'account_id' => $fixedAsset->account_id,
    'is_transferred' => false,
]);*/