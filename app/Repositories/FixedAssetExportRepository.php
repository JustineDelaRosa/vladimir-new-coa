<?php

namespace App\Repositories;

use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Models\Formula;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;

class FixedAssetExportRepository
{
    protected $calculationRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
    }

    public function FixedAssetExport($search, $startDate, $endDate)
    {
        $firstQuery = FixedAsset::select([
            'id',
            'capex_id',
            'sub_capex_id',
            'vladimir_tag_number',
            'tag_number',
            'tag_number_old',
            'asset_description',
            'type_of_request_id',
            'asset_specification',
            'accountability',
            'accountable',
            'capitalized',
            'cellphone_number',
            'brand',
            'major_category_id',
            'minor_category_id',
            'voucher',
            'receipt',
            'quantity',
            'depreciation_method',
            'acquisition_cost',
            'asset_status_id',
            'cycle_count_status_id',
            'depreciation_status_id',
            'movement_status_id',
            'is_old_asset',
            'is_additional_cost',
            'care_of',
            'company_id',
            'department_id',
            'location_id',
            'account_id',
            'formula_id',
            'created_at',
        ])->where('is_active', 1);

        $secondQuery = AdditionalCost::select([
            'additional_costs.id',
            'fixed_assets.capex_id AS capex_id',
            'fixed_assets.sub_capex_id AS sub_capex_id',
            'fixed_assets.vladimir_tag_number AS vladimir_tag_number',
            'fixed_assets.tag_number AS tag_number',
            'fixed_assets.tag_number_old AS tag_number_old',
            'additional_costs.asset_description',
            'additional_costs.type_of_request_id',
            'additional_costs.asset_specification',
            'additional_costs.accountability',
            'additional_costs.accountable',
            'additional_costs.capitalized',
            'additional_costs.cellphone_number',
            'additional_costs.brand',
            'additional_costs.major_category_id',
            'additional_costs.minor_category_id',
            'additional_costs.voucher',
            'additional_costs.receipt',
            'additional_costs.quantity',
            'additional_costs.depreciation_method',
            'additional_costs.acquisition_cost',
            'additional_costs.asset_status_id',
            'additional_costs.cycle_count_status_id',
            'additional_costs.depreciation_status_id',
            'additional_costs.movement_status_id',
            'fixed_assets.is_old_asset as is_old_asset',
            'additional_costs.is_additional_cost',
            'additional_costs.care_of',
            'additional_costs.company_id',
            'additional_costs.department_id',
            'additional_costs.location_id',
            'additional_costs.account_id',
            'additional_costs.formula_id',
            'fixed_assets.created_at'
        ])->where('additional_costs.is_active', 1)
            ->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id');


        if ((!empty($startDate) && empty($endDate)) || (empty($startDate) && !empty($endDate))) {
            return response()->json(['error' => 'Please fill both start date and end date'], 400);
        }


//        if ($search != null && ($startDate == null && $endDate == null)) {
//            // apply the search condition on the firstQuery
//            $firstQuery->Where('vladimir_tag_number', 'LIKE', "%$search%")
//                ->orWhere('tag_number', 'LIKE', "%$search%")
//                ->orWhere('tag_number_old', 'LIKE', "%$search%")
//                ->orWhere('accountability', 'LIKE', "%$search%")
//                ->orWhere('accountable', 'LIKE', "%$search%")
//                ->orWhere('brand', 'LIKE', "%$search%")
//                ->orWhere('depreciation_method', 'LIKE', "%$search%");
//            $firstQuery->orWhereHas('subCapex', function ($query) use ($search) {
//                $query->where('sub_capex', 'LIKE', '%' . $search . '%')
//                    ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('majorCategory', function ($query) use ($search) {
//                $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('minorCategory', function ($query) use ($search) {
//                $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('department.division', function ($query) use ($search) {
//                $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('assetStatus', function ($query) use ($search) {
//                $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('cycleCountStatus', function ($query) use ($search) {
//                $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('depreciationStatus', function ($query) use ($search) {
//                $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('movementStatus', function ($query) use ($search) {
//                $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('location', function ($query) use ($search) {
//                $query->where('location_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('company', function ($query) use ($search) {
//                $query->where('company_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('department', function ($query) use ($search) {
//                $query->where('department_name', 'LIKE', '%' . $search . '%');
//            });
//            $firstQuery->orWhereHas('accountTitle', function ($query) use ($search) {
//                $query->where('account_title_name', 'LIKE', '%' . $search . '%');
//            });
//
//
//            $secondQuery->Where('vladimir_tag_number', 'LIKE', "%$search%")
//                ->orWhere('tag_number', 'LIKE', "%$search%")
//                ->orWhere('tag_number_old', 'LIKE', "%$search%")
//                ->orWhere('additional_costs.accountability', 'LIKE', "%$search%")
//                ->orWhere('additional_costs.accountable', 'LIKE', "%$search%")
//                ->orWhere('additional_costs.brand', 'LIKE', "%$search%")
//                ->orWhere('additional_costs.depreciation_method', 'LIKE', "%$search%");
//            $secondQuery->orWhereHas('fixedAsset.subCapex', function ($query) use ($search) {
//                $query->where('sub_capex', 'LIKE', '%' . $search . '%')
//                    ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('majorCategory', function ($query) use ($search) {
//                $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('minorCategory', function ($query) use ($search) {
//                $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('department.division', function ($query) use ($search) {
//                $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('assetStatus', function ($query) use ($search) {
//                $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('cycleCountStatus', function ($query) use ($search) {
//                $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('depreciationStatus', function ($query) use ($search) {
//                $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('movementStatus', function ($query) use ($search) {
//                $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('location', function ($query) use ($search) {
//                $query->where('location_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('company', function ($query) use ($search) {
//                $query->where('company_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('department', function ($query) use ($search) {
//                $query->where('department_name', 'LIKE', '%' . $search . '%');
//            });
//            $secondQuery->orWhereHas('accountTitle', function ($query) use ($search) {
//                $query->where('account_title_name', 'LIKE', '%' . $search . '%');
//            });
//        }
//        if (($startDate && $endDate) || $search) {
//            $firstQuery->withTrashed()->with([
//                'formula' => function ($query) {
//                    $query->withTrashed();
//                },
//            ]);
//
//// Add date filter if both startDate and endDate are given
//            if ($startDate && $endDate) {
//                $firstQuery->whereBetween('created_at', [$startDate, $endDate]);
//            }
//
//// Add search filter if search is given
//            if ($search) {
//                $firstQuery->where(function ($query) use ($search) {
//                    $query->Where('vladimir_tag_number', 'LIKE', "%$search%")
//                        ->orWhere('tag_number', 'LIKE', "%$search%")
//                        ->orWhere('tag_number_old', 'LIKE', "%$search%")
//                        ->orWhere('accountability', 'LIKE', "%$search%")
//                        ->orWhere('accountable', 'LIKE', "%$search%")
//                        ->orWhere('brand', 'LIKE', "%$search%")
//                        ->orWhere('depreciation_method', 'LIKE', "%$search%");
//                    $query->orWhereHas('subCapex', function ($query) use ($search) {
//                        $query->where('sub_capex', 'LIKE', '%' . $search . '%')
//                            ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('majorCategory', function ($query) use ($search) {
//                        $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('minorCategory', function ($query) use ($search) {
//                        $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('department.division', function ($query) use ($search) {
//                        $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('assetStatus', function ($query) use ($search) {
//                        $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('cycleCountStatus', function ($query) use ($search) {
//                        $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('depreciationStatus', function ($query) use ($search) {
//                        $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('movementStatus', function ($query) use ($search) {
//                        $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('location', function ($query) use ($search) {
//                        $query->where('location_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('company', function ($query) use ($search) {
//                        $query->where('company_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('department', function ($query) use ($search) {
//                        $query->where('department_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('accountTitle', function ($query) use ($search) {
//                        $query->where('account_title_name', 'LIKE', '%' . $search . '%');
//                    });
//                });
//            }
//
//
//            $secondQuery->withTrashed()->with([
//                'formula' => function ($query) {
//                    $query->withTrashed();
//                },
//            ]);
//
//// Add date filter if both startDate and endDate are given
//            if ($startDate && $endDate) {
//                $secondQuery->whereBetween('fixed_assets.created_at', [$startDate, $endDate]);
//            }
//
//// Add search filter if search is given
//            if ($search) {
//                $secondQuery->where(function ($query) use ($search) {
//                    $query->Where('vladimir_tag_number', 'LIKE', "%$search%")
//                        ->orWhere('tag_number', 'LIKE', "%$search%")
//                        ->orWhere('tag_number_old', 'LIKE', "%$search%")
//                        ->orWhere('additional_costs.accountability', 'LIKE', "%$search%")
//                        ->orWhere('additional_costs.accountable', 'LIKE', "%$search%")
//                        ->orWhere('additional_costs.brand', 'LIKE', "%$search%")
//                        ->orWhere('additional_costs.depreciation_method', 'LIKE', "%$search%");
//                    $query->orWhereHas('fixedAsset.subCapex', function ($query) use ($search) {
//                        $query->where('sub_capex', 'LIKE', '%' . $search . '%')
//                            ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('majorCategory', function ($query) use ($search) {
//                        $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('minorCategory', function ($query) use ($search) {
//                        $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('department.division', function ($query) use ($search) {
//                        $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('assetStatus', function ($query) use ($search) {
//                        $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('cycleCountStatus', function ($query) use ($search) {
//                        $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('depreciationStatus', function ($query) use ($search) {
//                        $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('movementStatus', function ($query) use ($search) {
//                        $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('location', function ($query) use ($search) {
//                        $query->where('location_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('company', function ($query) use ($search) {
//                        $query->where('company_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('department', function ($query) use ($search) {
//                        $query->where('department_name', 'LIKE', '%' . $search . '%');
//                    });
//                    $query->orWhereHas('accountTitle', function ($query) use ($search) {
//                        $query->where('account_title_name', 'LIKE', '%' . $search . '%');
//                    });
//                });
//            }
//
//        }

        $firstQuery = $this->applyFilters($firstQuery, $search, $startDate, $endDate);
        $secondQuery = $this->applyFilters($secondQuery, $search, $startDate, $endDate,
            'fixed_assets.created_at', 'fixedAsset.subCapex', 'fixed_assets.accountability',
            'fixed_assets.accountable', 'fixed_assets.brand', 'fixed_assets.depreciation_method', 'fixed_assets.is_active');

        $results = $firstQuery->unionAll($secondQuery)->orderBy('vladimir_tag_number')->get();
        //if results are empty
        if ($results->isEmpty()) {
            return response()->json([
                'message' => 'Invalid search',
                'errors' => [
                    'search' => [
                        'No data found'
                    ]
                ]
            ], 422);
        }


//        return $results;
        return $this->refactorExport($results);
    }

    private function refactorExport($fixedAssets): array
    {


        $fixed_assets_arr = [];
        foreach ($fixedAssets as $fixed_asset) {
            $formula = Formula::where('id', $fixed_asset->formula_id)->first();
            $depreciation_rate = $this->calculateDepreciationRates($fixed_asset, $formula);
            $accumulated_cost = $this->calculateAccumulatedCost($fixed_asset, $depreciation_rate);

            $fixed_assets_arr[] = [
                'id' => $fixed_asset->id,
                'capex' => $fixed_asset->capex->capex ?? '-',
                'project_name' => $fixed_asset->capex->project_name ?? '-',
                'sub_capex' => $fixed_asset->subCapex->sub_capex ?? '-',
                'sub_project' => $fixed_asset->subCapex->sub_project ?? '-',
                'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
                'tag_number' => $fixed_asset->tag_number,
                'tag_number_old' => $fixed_asset->tag_number_old,
                'asset_description' => $fixed_asset->asset_description,
                'type_of_request' => $fixed_asset->typeOfRequest->type_of_request_name,
                'asset_specification' => $fixed_asset->asset_specification,
                'accountability' => $fixed_asset->accountability,
                'accountable' => $fixed_asset->accountable,
                'brand' => $fixed_asset->brand,
                'division' => $fixed_asset->department->division->division_name,
                'major_category' => $fixed_asset->majorCategory->major_category_name,
                'minor_category' => $fixed_asset->minorCategory->minor_category_name,
                'capitalized' => $fixed_asset->capitalized,
                'cellphone_number' => $fixed_asset->cellphone_number,
                'voucher' => $fixed_asset->voucher,
                'receipt' => $fixed_asset->receipt,
                'quantity' => $fixed_asset->quantity,
                'depreciation_method' => $fixed_asset->depreciation_method,
                'est_useful_life' => $fixed_asset->MajorCategory->est_useful_life,
                'acquisition_date' => $fixed_asset->formula->acquisition_date,
                'acquisition_cost' => $fixed_asset->formula->acquisition_cost,
                'scrap_value' => $fixed_asset->formula->scrap_value,
                'depreciable_basis' => $fixed_asset->formula->depreciable_basis,
                'accumulated_cost' => $accumulated_cost,
                'asset_status' => $fixed_asset->assetStatus->asset_status_name,
                'cycle_count_status' => $fixed_asset->cycleCountStatus->cycle_count_status_name,
                'depreciation_status' => $fixed_asset->depreciationStatus->depreciation_status_name,
                'movement_status' => $fixed_asset->movementStatus->movement_status_name,
                'care_of' => $fixed_asset->care_of,
                'months_depreciated' => $depreciation_rate['monthDepreciated'],
                'end_depreciation' => $fixed_asset->formula->end_depreciation,
                'depreciation_per_year' => $depreciation_rate['yearly'],
                'depreciation_per_month' => $depreciation_rate['monthly'],
                'remaining_book_value' => $this->calculateRemainingBookValue($fixed_asset, $accumulated_cost),
                'release_date' => $fixed_asset->formula->release_date,
                'start_depreciation' => $fixed_asset->formula->start_depreciation,
                'company_code' => $fixed_asset->company->company_code,
                'company_name' => $fixed_asset->company->company_name,
                'department_code' => $fixed_asset->department->department_code,
                'department_name' => $fixed_asset->department->department_name,
                'location_code' => $fixed_asset->location->location_code,
                'location_name' => $fixed_asset->location->location_name,
                'account_title_code' => $fixed_asset->accountTitle->account_title_code,
                'account_title_name' => $fixed_asset->accountTitle->account_title_name
            ];
        }
        return $fixed_assets_arr;
    }

    private function calculateDepreciationRates($fixed_asset, $formula): array
    {
        return [
            'monthly' => $this->depreciationPerMonth($formula->depreciable_basis, $formula->scrap_value, $fixed_asset->MajorCategory->est_useful_life),
            'yearly' => $this->depreciationPerYear($formula->depreciable_basis, $formula->scrap_value, $fixed_asset->MajorCategory->est_useful_life),
            'monthDepreciated' => $this->monthDepreciated($formula->start_depreciation),
        ];
    }

    private function calculateAccumulatedCost($fixed_asset, $depreciation_rate)
    {
        return $this->calculationRepository->getAccumulatedCost($depreciation_rate['monthly'], $this->monthDepreciated($fixed_asset->formula->start_depreciation));
    }

    private function calculateRemainingBookValue($fixed_asset, $accumulated_cost)
    {
        return $this->calculationRepository->getRemainingBookValue($fixed_asset->formula->depreciable_basis, $accumulated_cost);
    }

    private function depreciationPerMonth($depreciable_basis, $scrap_value, $est_useful_life)
    {
        return $this->calculationRepository->getMonthlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life);
    }

    private function depreciationPerYear($depreciable_basis, $scrap_value, $est_useful_life)
    {
        return $this->calculationRepository->getYearlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life);
    }

    private function monthDepreciated($start_depreciation)
    {
        $current_date = Carbon::now();

        return $this->calculationRepository->getMonthDifference($start_depreciation, $current_date);
    }


    function applyFilters($query, $search, $startDate, $endDate,
                          $created_at = 'created_at',
                          $relation = 'subCapex',
                          $accountability = 'accountability',
                          $accountable = 'accountable',
                          $brand = 'brand',
                          $depreciation_method = 'depreciation_method',
                          $is_active = 'is_active')
    {

        $query->where($is_active, 1);
        if ($search != null && ($startDate == null && $endDate == null)) {
            $query->where(function ($q) use ($relation, $depreciation_method, $brand, $accountable, $accountability, $search) {
                $queryConditions = [
                    'vladimir_tag_number',
                    'tag_number',
                    'tag_number_old',
                    $accountability,
                    $accountable,
                    $brand,
                    $depreciation_method];

                foreach ($queryConditions as $condition) {
                    $q->orWhere($condition, 'LIKE', "%$search%");
                }

                $q->orWhereHas($relation, function ($query) use ($search) {
                    $query->where('sub_capex', 'LIKE', '%' . $search . '%')
                        ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('minorCategory', function ($query) use ($search) {
                    $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('department.division', function ($query) use ($search) {
                    $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('assetStatus', function ($query) use ($search) {
                    $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('cycleCountStatus', function ($query) use ($search) {
                    $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('depreciationStatus', function ($query) use ($search) {
                    $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('movementStatus', function ($query) use ($search) {
                    $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('location', function ($query) use ($search) {
                    $query->where('location_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('company', function ($query) use ($search) {
                    $query->where('company_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('department', function ($query) use ($search) {
                    $query->where('department_name', 'LIKE', '%' . $search . '%');
                });
                $q->orWhereHas('accountTitle', function ($query) use ($search) {
                    $query->where('account_title_name', 'LIKE', '%' . $search . '%');
                });
            });
        }

        if (($startDate && $endDate) || $search) {

            if ($startDate && $endDate) {
                //Ensure the dates are in Y-m-d H:i:s format
                $startDate = new DateTime($startDate);
                $endDate = new DateTime($endDate);

                //set time to end of day
                $endDate->setTime(23, 59, 59);

                $query->whereBetween($created_at, [$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
            }

            if ($search) {
                $query->where(function ($q) use ($relation, $depreciation_method, $brand, $accountable, $accountability, $search) {
                    $queryConditions = [
                        'vladimir_tag_number',
                        'tag_number',
                        'tag_number_old',
                        $accountability,
                        $accountable,
                        $brand,
                        $depreciation_method];

                    foreach ($queryConditions as $condition) {
                        $q->orWhere($condition, 'LIKE', "%$search%");
                    }

                    $q->orWhereHas($relation, function ($query) use ($search) {
                        $query->where('sub_capex', 'LIKE', '%' . $search . '%')
                            ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('majorCategory', function ($query) use ($search) {
                        $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('minorCategory', function ($query) use ($search) {
                        $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('department.division', function ($query) use ($search) {
                        $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('assetStatus', function ($query) use ($search) {
                        $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('cycleCountStatus', function ($query) use ($search) {
                        $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('depreciationStatus', function ($query) use ($search) {
                        $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('movementStatus', function ($query) use ($search) {
                        $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('location', function ($query) use ($search) {
                        $query->where('location_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('company', function ($query) use ($search) {
                        $query->where('company_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('department', function ($query) use ($search) {
                        $query->where('department_name', 'LIKE', '%' . $search . '%');
                    });
                    $q->orWhereHas('accountTitle', function ($query) use ($search) {
                        $query->where('account_title_name', 'LIKE', '%' . $search . '%');
                    });
                });
            }
        }
        return $query;
    }

}
