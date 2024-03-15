<?php

namespace App\Repositories;

use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Models\Formula;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FixedAssetExportRepository
{
    protected $calculationRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
    }

    public function export($search = null, $startDate = null, $endDate = null)
    {
        $fixedAsset = FixedAsset::leftJoin('users', 'fixed_assets.requester_id', '=', 'users.id')
            ->leftJoin('type_of_requests', 'fixed_assets.type_of_request_id', '=', 'type_of_requests.id')
            ->leftJoin('suppliers', 'fixed_assets.supplier_id', '=', 'suppliers.id')
            ->leftJoin('companies', 'fixed_assets.company_id', '=', 'companies.id')
            ->leftJoin('business_units', 'fixed_assets.business_unit_id', '=', 'business_units.id')
            ->leftJoin('departments', 'fixed_assets.department_id', '=', 'departments.id')
            ->leftJoin('units', 'fixed_assets.unit_id', '=', 'units.id')
            ->leftJoin('sub_units', 'fixed_assets.subunit_id', '=', 'sub_units.id')
            ->leftJoin('locations', 'fixed_assets.location_id', '=', 'locations.id')
            ->leftJoin('account_titles', 'fixed_assets.account_id', '=', 'account_titles.id')
            ->leftJoin('formulas', 'fixed_assets.formula_id', '=', 'formulas.id')
            ->leftjoin('capexes', 'fixed_assets.capex_id', '=', 'capexes.id')
            ->leftjoin('sub_capexes', 'fixed_assets.sub_capex_id', '=', 'sub_capexes.id')
            ->leftJoin('major_categories', 'fixed_assets.major_category_id', '=', 'major_categories.id')
            ->leftJoin('minor_categories', 'fixed_assets.minor_category_id', '=', 'minor_categories.id')
            ->leftJoin('divisions', 'departments.division_id', '=', 'divisions.id')
            ->leftJoin('asset_statuses', 'fixed_assets.asset_status_id', '=', 'asset_statuses.id')
            ->leftJoin('cycle_count_statuses', 'fixed_assets.cycle_count_status_id', '=', 'cycle_count_statuses.id')
            ->leftJoin('depreciation_statuses', 'fixed_assets.depreciation_status_id', '=', 'depreciation_statuses.id')
            ->leftJoin('movement_statuses', 'fixed_assets.movement_status_id', '=', 'movement_statuses.id');


        if ($search) {
            // ... search logic ...
        }

        if ($startDate && $endDate) {
            $fixedAsset->whereBetween('fixed_assets.created_at', [$startDate, $endDate]);
        }

        $fixedAsset = $fixedAsset->select(
                'fixed_assets.id',
                'users.username as requester',
                'capexes.capex as capex',
                'capexes.project_name as project_name',
                'sub_capexes.sub_capex as sub_capex',
                'sub_capexes.sub_project as sub_project',
                'transaction_number',
                'reference_number',
                'pr_number',
                'po_number',
                'vladimir_tag_number',
                'tag_number',
                'tag_number_old',
                'asset_description',
                'asset_specification',
                'type_of_requests.type_of_request_name as type_of_request',
                'suppliers.supplier_name as supplier',
                'accountability',
                'accountable',
                'received_by',
                'capitalized',
                'cellphone_number',
                'brand',
                'major_categories.major_category_name as major_category',
                'minor_categories.minor_category_name as minor_category',
                'divisions.division_name as division',
                'voucher',
                'voucher_date',
                'receipt',
                'quantity',
                'fixed_assets.acquisition_date',
                'fixed_assets.acquisition_cost',
                'charged_department',
                'asset_statuses.asset_status_name as asset_status',
                'cycle_count_statuses.cycle_count_status_name as cycle_count_status',
                'depreciation_statuses.depreciation_status_name as depreciation_status',
                'movement_statuses.movement_status_name as movement_status',
                'care_of',
                'companies.company_code as company_code',
                'companies.company_name as company_name',
                'business_units.business_unit_code as business_unit_code',
                'business_units.business_unit_name as business_unit_name',
                'departments.department_code as department_code',
                'departments.department_name as department_name',
                'units.unit_code as unit_code',
                'units.unit_name as unit_name',
                'sub_units.sub_unit_code as sub_unit_code',
                'sub_units.sub_unit_name as sub_unit_name',
                'locations.location_code as location_code',
                'locations.location_name as location_name',
                'account_titles.account_title_code as account_title_code',
                'account_titles.account_title_name as account_title_name',
                DB::raw('NULL as add_cost_sequence'),
                'formulas.depreciation_method',
                'formulas.scrap_value',
                'major_categories.est_useful_life', // todo: subject to monitor for possible bug
                'formulas.depreciable_basis',
                'formulas.months_depreciated',
                'formulas.end_depreciation',
                'formulas.depreciation_per_year',
                'formulas.depreciation_per_month',
                'formulas.accumulated_cost',
                'formulas.remaining_book_value',
                'formulas.release_date',
                'formulas.start_depreciation',
//                'fixed_assets.created_at'
            );


        $additionalCost = AdditionalCost::leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            ->leftJoin('users', 'additional_costs.requester_id', '=', 'users.id')
            ->leftJoin('type_of_requests', 'additional_costs.type_of_request_id', '=', 'type_of_requests.id')
            ->leftJoin('suppliers', 'additional_costs.supplier_id', '=', 'suppliers.id')
            ->leftJoin('companies', 'additional_costs.company_id', '=', 'companies.id')
            ->leftJoin('business_units', 'additional_costs.business_unit_id', '=', 'business_units.id')
            ->leftJoin('departments', 'additional_costs.department_id', '=', 'departments.id')
            ->leftJoin('units', 'additional_costs.unit_id', '=', 'units.id')
            ->leftJoin('sub_units', 'additional_costs.subunit_id', '=', 'sub_units.id')
            ->leftJoin('locations', 'additional_costs.location_id', '=', 'locations.id')
            ->leftJoin('account_titles', 'additional_costs.account_id', '=', 'account_titles.id')
            ->leftJoin('formulas', 'additional_costs.formula_id', '=', 'formulas.id')
            ->leftJoin('capexes', 'fixed_assets.capex_id', '=', 'capexes.id')
            ->leftJoin('sub_capexes', 'fixed_assets.sub_capex_id', '=', 'sub_capexes.id')
            ->leftJoin('major_categories', 'fixed_assets.major_category_id', '=', 'major_categories.id')
            ->leftJoin('minor_categories', 'fixed_assets.minor_category_id', '=', 'minor_categories.id')
            ->leftJoin('divisions', 'departments.division_id', '=', 'divisions.id')
            ->leftJoin('asset_statuses', 'fixed_assets.asset_status_id', '=', 'asset_statuses.id')
            ->leftJoin('cycle_count_statuses', 'fixed_assets.cycle_count_status_id', '=', 'cycle_count_statuses.id')
            ->leftJoin('depreciation_statuses', 'fixed_assets.depreciation_status_id', '=', 'depreciation_statuses.id')
            ->leftJoin('movement_statuses', 'fixed_assets.movement_status_id', '=', 'movement_statuses.id');


        if ($search) {
            // ... search logic ...
        }

        if ($startDate && $endDate) {
            $additionalCost->whereBetween('fixed_assets.created_at', [$startDate, $endDate]);
        }

        $additionalCost = $additionalCost->select(
            'additional_costs.id',
            'users.username as requester',
            'capexes.capex as capex',
            'capexes.project_name as project_name',
            'sub_capexes.sub_capex as sub_capex',
            'sub_capexes.sub_project as sub_project',
            'additional_costs.transaction_number',
            'additional_costs.reference_number',
            'additional_costs.pr_number',
            'additional_costs.po_number',
            'fixed_assets.vladimir_tag_number as vladimir_tag_number',
            'fixed_assets.tag_number',
            'fixed_assets.tag_number_old',
            'additional_costs.asset_description',
            'additional_costs.asset_specification',
            'type_of_requests.type_of_request_name as type_of_request',
            'suppliers.supplier_name',
            'additional_costs.accountability',
            'additional_costs.accountable',
            'additional_costs.received_by',
            'additional_costs.capitalized',
            'additional_costs.cellphone_number',
            'additional_costs.brand',
            'major_categories.major_category_name as major_category',
            'minor_categories.minor_category_name as minor_category',
            'divisions.division_name as division',
            'additional_costs.voucher',
            'additional_costs.voucher_date',
            'additional_costs.receipt',
            'additional_costs.quantity',
            'additional_costs.acquisition_date',
            'additional_costs.acquisition_cost',
            'fixed_assets.charged_department',
            'asset_statuses.asset_status_name as asset_status',
            'cycle_count_statuses.cycle_count_status_name as cycle_count_status',
            'depreciation_statuses.depreciation_status_name as depreciation_status',
            'movement_statuses.movement_status_name as movement_status',
            'fixed_assets.care_of',
            'companies.company_code as company_code',
            'companies.company_name as company_name',
            'business_units.business_unit_code as business_unit_code',
            'business_units.business_unit_name as business_unit_name',
            'departments.department_code as department_code',
            'departments.department_name as department_name',
            'units.unit_code as unit_code',
            'units.unit_name as unit_name',
            'sub_units.sub_unit_code as sub_unit_code',
            'sub_units.sub_unit_name as sub_unit_name',
            'locations.location_code as location_code',
            'locations.location_name as location_name',
            'account_titles.account_title_code as account_title_code',
            'account_titles.account_title_name as account_title_name',
            'additional_costs.add_cost_sequence',
            'formulas.depreciation_method',
            'formulas.scrap_value',
            'major_categories.est_useful_life', // todo: subject to monitor for possible bug
            'formulas.depreciable_basis',
            'formulas.months_depreciated',
            'formulas.end_depreciation',
            'formulas.depreciation_per_year',
            'formulas.depreciation_per_month',
            'formulas.accumulated_cost',
            'formulas.remaining_book_value',
            'formulas.release_date',
            'formulas.start_depreciation',
//            'fixed_assets.created_at'
        );

        return $fixedAsset->unionAll($additionalCost)->orderBy('vladimir_tag_number', 'ASC')->get();
    }

    function applyFilters(
        $query,
        $search,
        $startDate,
        $endDate,
        $created_at = 'created_at',
        $relation = 'subCapex',
        $accountability = 'accountability',
        $accountable = 'accountable',
        $brand = 'brand',
        $depreciation_method = 'depreciation_method',
        $is_active = 'is_active'
    )
    {
        $query->where($is_active, 1);
        //        if ($search != null && ($startDate == null && $endDate == null)) {
        //
        //            $query->where(function ($q) use ($relation, $depreciation_method, $brand, $accountable, $accountability, $search) {
        //                $queryConditions = [
        //                    'vladimir_tag_number',
        //                    'tag_number',
        //                    'tag_number_old',
        //                    $accountability,
        //                    $accountable,
        //                    $brand,
        //                    $depreciation_method];
        //
        //                foreach ($queryConditions as $condition) {
        //                    $q->orWhere($condition, 'LIKE', "%$search%");
        //                }
        //                $q->orWhereHas('typeOfRequest', function ($query) use ($search) {
        //                    $query->where('type_of_request_name', $search);
        //                });
        //                $q->orWhereHas($relation, function ($query) use ($search) {
        //                    $query->where('sub_capex', 'LIKE', '%' . $search . '%')
        //                        ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('majorCategory', function ($query) use ($search) {
        //                    $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('minorCategory', function ($query) use ($search) {
        //                    $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('department.division', function ($query) use ($search) {
        //                    $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('assetStatus', function ($query) use ($search) {
        //                    $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('cycleCountStatus', function ($query) use ($search) {
        //                    $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('depreciationStatus', function ($query) use ($search) {
        //                    $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('movementStatus', function ($query) use ($search) {
        //                    $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('location', function ($query) use ($search) {
        //                    $query->where('location_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('company', function ($query) use ($search) {
        //                    $query->where('company_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('department', function ($query) use ($search) {
        //                    $query->where('department_name', 'LIKE', '%' . $search . '%');
        //                });
        //                $q->orWhereHas('accountTitle', function ($query) use ($search) {
        //                    $query->where('account_title_name', 'LIKE', '%' . $search . '%');
        //                });
        //            });
        //        }

        if (($startDate || $endDate) || $search) {

            //            if ($startDate && $endDate) {
            //                //Ensure the dates are in Y-m-d H:i:s format
            //                $startDate = new DateTime($startDate);
            //                $endDate = new DateTime($endDate);
            //
            //                //set time to end of day
            //                $endDate->setTime(23, 59, 59);
            //
            //                $query->whereBetween($created_at, [$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
            //            }

            if ($startDate) {
                $startDate = new DateTime($startDate);
                $query->where($created_at, '>=', $startDate->format('Y-m-d H:i:s'));
            }

            if ($endDate) {
                $endDate = new DateTime($endDate);
                //set time to an end of day
                $endDate->setTime(23, 59, 59);
                $query->where($created_at, '<=', $endDate->format('Y-m-d H:i:s'));
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
                        $depreciation_method
                    ];

                    foreach ($queryConditions as $condition) {
                        $q->orWhere($condition, 'LIKE', "%$search%");
                    }
                    $q->orWhereHas('typeOfRequest', function ($query) use ($search) {
                        $query->where('type_of_request_name', $search);
                    });
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
