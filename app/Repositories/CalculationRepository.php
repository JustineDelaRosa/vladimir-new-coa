<?php

namespace App\Repositories;

use App\Imports\AdditionalCostImport;
use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Models\Status\DepreciationStatus;
use Carbon\Carbon;

class CalculationRepository
{
    public function getMonthDifference($start_depreciation, $current_month)
    {
        $start_depreciation = Carbon::parse($start_depreciation);
        $current_month = Carbon::parse($current_month);
        return $start_depreciation->diffInMonths($current_month->addMonth(1));
    }

    public function getMonthlyDepreciation($acquisition_cost, $scrap_value, $est_useful_life): float
    {
        //if acquisition cost and scrap value are equal to zero, return zero
        if ($acquisition_cost == 0 && $scrap_value == 0 || ($est_useful_life == 0)) {
            return 0;
        }
        $est_useful_life = floor($est_useful_life) * 12 + (($est_useful_life - floor($est_useful_life)) * 12);
        return round(($acquisition_cost - $scrap_value) / $est_useful_life, 2);
    }

    public function getYearlyDepreciation($acquisition_cost, $scrap_value, $est_useful_life): float
    {
        if ($acquisition_cost == 0 && $scrap_value == 0 || ($est_useful_life == 0)) {
            return 0;
        }
        $est_useful_life = floor($est_useful_life) + (($est_useful_life - floor($est_useful_life)) * 12) / 12;
        return round(($acquisition_cost - $scrap_value) / $est_useful_life, 2);
    }

    public function getAccumulatedCost($monthly_depreciation, float $custom_age, $depreciable_basis)
    {
        $accumulated_cost = $monthly_depreciation * $custom_age;
        //if the accumulated cost is greater than the depreciable basis, return the depreciable basis
        if ($accumulated_cost > $depreciable_basis) {
            return $depreciable_basis;
        }
        return round($accumulated_cost, 2);
    }

    public function getRemainingBookValue($acquisition_cost, float $accumulated_cost): float
    {
        $remaining_book_value = $acquisition_cost - $accumulated_cost;
        //if the remaining book value is less than zero, return zero
        if ($remaining_book_value < 0) {
            return 0;
        }
        return round($remaining_book_value);
    }

    public function getEndDepreciation($start_depreciation, $est_useful_life, $depreciation_method): ?string
    {
        $start_depreciation = Carbon::parse($start_depreciation);

        if ($depreciation_method == 'One Time') {
            $end_depreciation = $start_depreciation->addMonth();
        } else {
            $years_added = floor($est_useful_life);
            $months_added = floor(($est_useful_life - $years_added) * 12);

            $end_depreciation = $start_depreciation->addYears($years_added)->addMonths($months_added)->subMonth(1);
        }

        return $end_depreciation->format('Y-m-d') ?? null;
    }

    //TODO: will change and used the voucher date
    public function getStartDepreciation($method, $release_date = null): ?string //$release_date
    {
        if ($method == 'One Time') {
            $release_date = Carbon::parse($release_date);
            return $release_date->addMonth()->format('Y-m-d') ?? null;
        } else {
            $release_date = Carbon::parse($release_date);
            return $release_date->addMonth(1)->format('Y-m-d') ?? null;
        }
    }

    public function dateValidation($date, $start_depreciation, $end_depreciation): bool
    {
        $date = Carbon::parse($date);
        $start_depreciation = Carbon::parse($start_depreciation)->subMonth();
        $end_depreciation = Carbon::parse($end_depreciation);
        if ($date->between($start_depreciation, $end_depreciation)) {
            return true;
        } else {
            return false;
        }
    }

    //get the total cost of the main asset and its additional costs
    public function getTotalCost($additional_costs, $asset = 0)
    {
        $total_cost = $asset;
        foreach ($additional_costs as $additional_cost) {
            $total_cost += $additional_cost->formula->acquisition_cost;
        }
        return $total_cost;
    }


    public function validationForDate($attribute, $value, $fail, $collections = null)
    {
        $newAttribute = preg_replace('/^\d+\./', '', $attribute);
        $newAttribute = str_replace('_', ' ', $newAttribute);
        if ($newAttribute === 'voucher date') {
            if (strlen($value) !== 8) {
                $fail('Invalid format');
                return;
            }
        } else {
            // Validate the year and month format
            if (strlen($value) !== 6) {
                $fail('Invalid format');
                return;
            }
        }

        // Extract the year and month
        $year = substr($value, 0, 4);
        $month = substr($value, 4, 2);
        $day = substr($value, 6, 2);


        // Check if the year is a valid number
        if (!is_numeric($year) || (int)$year < 1900 || (int)$year > 2100) {
            $fail("Invalid year in the $newAttribute format");
            return;
        }

        // Check if the month is a valid number
        if (!is_numeric($month) || (int)$month < 1 || (int)$month > 12) {
            $fail("Invalid month in the $newAttribute format");
            return;
        }
        if ($newAttribute === 'voucher date') {
            $voucherDates = [];
            $voucherValues = [];

            if (!checkdate($month, $day, $year)) {
                $fail("Invalid $newAttribute format");
                return;
            }

            foreach ($collections as $collection) {
                $year = substr($collection['voucher_date'], 0, 4);
                $month = substr($collection['voucher_date'], 4, 2);
                $day = substr($collection['voucher_date'], 6, 2);

                if (checkdate((int)$month, (int)$day, (int)$year)) {
                    $date = Carbon::createFromFormat('Y-m-d', "$year-$month-$day");
                    $voucherDates[$collection['voucher']][] = $date->format('Y-m-d');
                }

                $voucherValues[] = $collection['voucher'];
            }

            // Checking for duplicates in the vouchers
            $uniqueVouchers = array_unique($voucherValues);
            if (count($uniqueVouchers) != count($voucherValues)) {
                asort($voucherValues);
                $duplicateVouchers = array_keys(array_filter(array_count_values($voucherValues), function ($count) {
                    return $count > 1;
                }));

                //Checking dates of duplicate vouchers for discrepancies
                foreach ($duplicateVouchers as $duplicateVoucher) {
                    // If voucher value is '-' then skip the current iteration
                    if ($duplicateVoucher == '-') {
                        continue;
                    }
                    $dates = $voucherDates[$duplicateVoucher];
                    if (count(array_unique($dates)) != 1) {
                        $fail('Same voucher with different date found');
                        return;
                    }
                }
            }

            //get current voucher value
            if (!is_array($collections)) {
                $collections = $collections->toArray();
            }
            $index = array_search($attribute, array_keys($collections));
            $voucherValue = $collections[$index]['voucher'];
            $matchingAssets = FixedAsset::where('voucher', $voucherValue)->get();
            $matchingAddCosts = AdditionalCost::where('voucher', $voucherValue)->get();
            $this->checkVoucherDate($matchingAssets, $value, $fail);
            $this->checkVoucherDate($matchingAddCosts, $value, $fail);

        }

        //if the $newAttribute is then add this validation
        if ($newAttribute == 'end depreciation') {
            if (!is_array($collections)) {
                $collections = $collections->toArray();
            }

            $index = array_search($attribute, array_keys($collections));

            $depreciation_status_name = $collections[$index]['depreciation_status'];
            $depreciation_status = DepreciationStatus::where('depreciation_status_name', $depreciation_status_name)->first();
            if ($depreciation_status->depreciation_status_name == 'Fully Depreciated') {
                //check if the value of end depreciation is not yet passed the current date (yyyymm)
                $current_date = Carbon::now()->format('Y-m');
                $value = substr_replace($value, '-', 4, 0);
                //check if the value is parsable or not
                if (Carbon::parse($value)->isAfter($current_date)) {
                    $fail('Not yet fully depreciated');
                }
            } elseif ($depreciation_status->depreciation_status_name == 'Running Depreciation') {
                //check if the value of end depreciation is not yet passed the current date (yyyymm)
                $current_date = Carbon::now()->format('Y-m');
                $value = substr_replace($value, '-', 4, 0);
                //check if the value is parsable or not
                if (Carbon::parse($value)->isBefore($current_date)) {
                    $fail('The asset is fully depreciated');
                }
            }
        }
    }


    function checkVoucherDate($items, $value, $fail)
    {
        $year = substr($value, 0, 4);
        $month = substr($value, 4, 2);
        $day = substr($value, 6, 2);
        $formattedValue = Carbon::createFromFormat('Y-m-d', "$year-$month-$day")->format('Y-m-d');

        foreach ($items as $item) {
            $uploaded_date = Carbon::parse($item->voucher_date)->format('Y-m-d');
            if ($uploaded_date != $formattedValue) {
                $fail('Same voucher with different date found');
                return;
            }
        }
    }

//    function checkVoucherDate($items, $value, $fail) {
//
//
//        foreach ($items as $item) {
//            $year = substr($value, 0, 4);
//            $month = substr($value, 4, 2);
//            $day = substr($value, 6, 2);
//            $uploaded_date = Carbon::parse($item->voucher_date)->format('Y-m-d');
////            echo "$value";
//            $value = Carbon::createFromFormat('Y-m-d', "$year-$month-$day")->format('Y-m-d');
//            if ($uploaded_date != $value) {
//                $fail('Same voucher with different date found');
//                return;
//            }
//        }
//    }


//    public function validationForDate($attribute, $value, $fail, $collections = null)
//    {
//        $newAttribute = $this->formatAttribute($attribute);
//        $this->validateDateFormat($newAttribute, $value, $fail);
//        $this->validateYearAndMonth($value, $newAttribute, $fail);
//
//        if ($newAttribute === 'voucher date') {
//            $this->validateVoucherDate($value, $collections, $fail);
//        }
//
//        if ($newAttribute == 'end depreciation') {
//            $this->validateEndDepreciation($value, $collections, $fail);
//        }
//    }
//
//    private function formatAttribute($attribute)
//    {
//        $newAttribute = preg_replace('/^\d+\./', '', $attribute);
//        return str_replace('_', ' ', $newAttribute);
//    }
//
//    private function validateDateFormat($newAttribute, $value, $fail)
//    {
//        $length = $newAttribute === 'voucher date' ? 8 : 6;
//        if (strlen($value) !== $length) {
//            $fail('Invalid format');
//        }
//    }
//
//    private function validateYearAndMonth($value, $newAttribute, $fail)
//    {
//        $year = substr($value, 0, 4);
//        $month = substr($value, 4, 2);
//
//        if (!is_numeric($year) || (int)$year < 1900 || (int)$year > 2100) {
//            $fail("Invalid year in the $newAttribute format");
//        }
//
//        if (!is_numeric($month) || (int)$month < 1 || (int)$month > 12) {
//            $fail("Invalid month in the $newAttribute format");
//        }
//    }
//
//    private function validateVoucherDate($value, $collections, $fail)
//    {
//        $voucherDates = [];
//        $voucherValues = [];
//        $day = substr($value, 6, 2);
//        $month = substr($value, 4, 2);
//        $year = substr($value, 0, 4);
//
//        if (!checkdate($month, $day, $year)) {
//            $fail("Invalid voucher date format");
//            return;
//        }
//
//        foreach ($collections as $collection) {
//            $year = substr($collection['voucher_date'], 0, 4);
//            $month = substr($collection['voucher_date'], 4, 2);
//            $day = substr($collection['voucher_date'], 6, 2);
//
//            if (checkdate((int)$month, (int)$day, (int)$year)) {
//                $date = Carbon::createFromFormat('Y-m-d', "$year-$month-$day");
//                $voucherDates[$collection['voucher']][] = $date->format('Y-m-d');
//            }
//
//            $voucherValues[] = $collection['voucher'];
//        }
//
//        $this->checkForDuplicateVouchers($voucherValues, $voucherDates, $fail);
//        $this->checkVoucherDateInDatabase($value, $collections, $fail);
//    }
//
//    private function checkForDuplicateVouchers($voucherValues, $voucherDates, $fail)
//    {
//        $uniqueVouchers = array_unique($voucherValues);
//        if (count($uniqueVouchers) != count($voucherValues)) {
//            asort($voucherValues);
//            $duplicateVouchers = array_keys(array_filter(array_count_values($voucherValues), function ($count) {
//                return $count > 1;
//            }));
//
//            foreach ($duplicateVouchers as $duplicateVoucher) {
//                if ($duplicateVoucher == '-') {
//                    continue;
//                }
//                $dates = $voucherDates[$duplicateVoucher];
//                if (count(array_unique($dates)) != 1) {
//                    $fail('Same voucher with different date found');
//                    return;
//                }
//            }
//        }
//    }
//
//    private function checkVoucherDateInDatabase($value, $collections, $fail)
//    {
//        if(!is_array($collections)) {
//            $collections = $collections->toArray();
//        }
//        $index = array_search('voucher_date', array_keys($collections));
//        $voucherValue = $collections[$index]['voucher'];
//        $matchingAssets = FixedAsset::where('voucher', $voucherValue)->get();
//        $matchingAddCosts = AdditionalCost::where('voucher', $voucherValue)->get();
//        $this->checkVoucherDate($matchingAssets, $value, $fail);
//        $this->checkVoucherDate($matchingAddCosts, $value, $fail);
//    }
//
//    private function validateEndDepreciation($value, $collections, $fail)
//    {
//        if (!is_array($collections)) {
//            $collections = $collections->toArray();
//        }
//
//        $index = array_search('end_depreciation', array_keys($collections));
//        $depreciation_status_name = $collections[$index]['depreciation_status'];
//        $depreciation_status = DepreciationStatus::where('depreciation_status_name', $depreciation_status_name)->first();
//        $current_date = Carbon::now()->format('Y-m');
//        $value = substr_replace($value, '-', 4, 0);
//
//        if ($depreciation_status->depreciation_status_name == 'Fully Depreciated' && Carbon::parse($value)->isAfter($current_date)) {
//            $fail('Not yet fully depreciated');
//        }
//
//        if ($depreciation_status->depreciation_status_name == 'Running Depreciation' && Carbon::parse($value)->isBefore($current_date)) {
//            $fail('The asset is fully depreciated');
//        }
//    }
}
