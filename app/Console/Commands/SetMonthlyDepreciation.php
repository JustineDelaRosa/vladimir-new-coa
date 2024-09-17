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
        $runningDepreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
        $fixed_assets = FixedAsset::with('formula')->where('depreciation_status_id', $runningDepreciationStatusId)->get();
        //check which one is to be depreciated today based on start deprecation date
        $selectedAsset = $fixed_assets->filter(function ($fixed_asset) {
            $startDepreciationDate = $fixed_asset->formula->start_depreciation;
            $endDepreciationDate = $fixed_asset->formula->end_depreciation;

            if (is_string($startDepreciationDate)) {
                $startDepreciationDate = Carbon::parse($startDepreciationDate);
            }

            if (is_string($endDepreciationDate)) {
                $endDepreciationDate = Carbon::parse($endDepreciationDate);
            }

            if ($startDepreciationDate->equalTo($endDepreciationDate)) {
                return $startDepreciationDate->isToday();
            }

            return $startDepreciationDate->lessThan(now()->addMonth())
                && $startDepreciationDate->format('d') == now()->addMonth()->format('d')
                && $endDepreciationDate->greaterThanOrEqualTo(now()->addMonth());

        })->values();

        foreach ($selectedAsset as $fixed_asset) {
            $isOneTime = $fixed_asset->depreciation_method == "One Time";
            //check if the asset has been depreciated before
            $depreciationHistory = DepreciationHistory::where('fixed_asset_id', $fixed_asset->id)->latest()->first();
            $agePerMonth = $this->calculationRepository->getMonthDifference($fixed_asset->formula->start_depreciation, now()->format('Y-m-d'));
            $monthlyDepreciation = $this->calculationRepository->getMonthlyDepreciation($fixed_asset->acquisition_cost, $fixed_asset->formula->scrap_value, $fixed_asset->majorCategory->est_useful_life);
            if (!$depreciationHistory) {
                $accumulatedDepreciation = $this->calculationRepository->getAccumulatedCost($monthlyDepreciation, $agePerMonth, $fixed_asset->formula->depreciable_basis);
            } else {
                //sum all the accumulated depreciation of $depreciationHistory
                $accumulatedDepreciation = $depreciationHistory->accumulated_depreciation + $monthlyDepreciation;
            }

            DepreciationHistory::create([
                'fixed_asset_id' => $fixed_asset->id,
                'depreciated_date' => now()->addMonth()->format('Y-m-d'),
//                now()->addMonth(15)->format('Y-m-d')
                'depreciated_amount_per_month' => $isOneTime ? $fixed_asset->acquisition_cost : $monthlyDepreciation,
                'accumulated_depreciation' => $isOneTime ? $fixed_asset->acquisition_cost : $accumulatedDepreciation,
                'book_value' => $isOneTime ? 0 : $fixed_asset->acquisition_cost - $accumulatedDepreciation,
                'depreciation_basis' => $fixed_asset->formula->depreciable_basis
            ]);
        }

        $this->info('Monthly depreciation set successfully.');
    }
}
