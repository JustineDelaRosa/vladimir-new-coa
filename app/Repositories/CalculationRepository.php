<?php

namespace App\Repositories;

use Carbon\Carbon;

class CalculationRepository
{
    public function getMonthDifference($start_depreciation, $current_month)
    {
        $start_depreciation = Carbon::parse($start_depreciation);
        $current_month = Carbon::parse($current_month);
        return $start_depreciation->diffInMonths($current_month->addMonth(1));
    }

    public function getMonthlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life): float
    {
        $est_useful_life = floor($est_useful_life) * 12 + (($est_useful_life - floor($est_useful_life)) * 12);
        return round(($depreciable_basis - $scrap_value) / $est_useful_life,2);
    }
    public function getYearlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life): float
    {
        $est_useful_life = floor($est_useful_life) + (($est_useful_life - floor($est_useful_life)) * 12) / 12;
        return round(($depreciable_basis - $scrap_value) / $est_useful_life,2);
    }
    public function getAccumulatedCost($monthly_depreciation, float $custom_age): float
    {
        $accumulated_cost = $monthly_depreciation * $custom_age;
        return round($accumulated_cost);
    }
    public function getRemainingBookValue($depreciable_basis, float $accumulated_cost): float
    {
        $remaining_book_value = $depreciable_basis - $accumulated_cost;
        return round($remaining_book_value);
    }
    public function getEndDepreciation($start_depreciation, $est_useful_life, $depreciation_method): string
    {
        $start_depreciation = Carbon::parse($start_depreciation);

        if ($depreciation_method == 'One Time') {
            $end_depreciation = $start_depreciation->addMonth();
        } else {
            $years_added = floor($est_useful_life);
            $months_added = floor(($est_useful_life - $years_added) * 12);

            $end_depreciation = $start_depreciation->addYears($years_added)->addMonths($months_added)->subMonth(1);
        }

        return $end_depreciation->format('Y-m');
    }
    public function getStartDepreciation($release_date): string
    {
        $release_date = Carbon::parse($release_date);
        return $release_date->addMonth(1)->format('Y-m');
    }
    public function dateValidation($date, $start_depreciation, $end_depreciation): bool
    {
        $date = Carbon::parse($date);
        $start_depreciation = Carbon::parse($start_depreciation);
        $end_depreciation = Carbon::parse($end_depreciation);
        if ($date->between($start_depreciation, $end_depreciation)) {
            return true;
        } else {
            return false;
        }
    }
    //get the total cost of the main asset and its additional costs
    public function getTotalCost($additional_costs, $asset = 0 )
    {
        $total_cost = $asset;
        foreach ($additional_costs as $additional_cost) {
            $total_cost += $additional_cost->formula->acquisition_cost;
        }
        return $total_cost;
    }


}
