<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AdditionalCostFilters extends QueryFilters
{
    protected array $allowedFilters = [
//        'vladimir_tag_number',
//        'tag_number',
//        'tag_number_old',
        'additional_costs.asset_description',
        'additional_costs.accountability',
        'additional_costs.accountable',
        'additional_costs.brand',
        'additional_costs.depreciation_method'
    ];

    protected array $columnSearch = [
//        'vladimir_tag_number',
//        'tag_number',
//        'tag_number_old',
        'additional_costs.asset_description',
        'additional_costs.accountability',
        'additional_costs.accountable',
        'additional_costs.brand',
        'additional_costs.depreciation_method'
    ];

    protected array $relationSearch = [
        'fixedAsset' => ['vladimir_tag_number', 'tag_number', 'tag_number_old'],
        'majorCategory' => ['major_category_name'],
        'minorCategory' => ['minor_category_name'],
        'department.division' => ['division_name'],
        'assetStatus' => ['asset_status_name'],
        'cycleCountStatus' => ['cycle_count_status_name'],
        'depreciationStatus' => ['depreciation_status_name'],
        'movementStatus' => ['movement_status_name'],
        'company' => ['company_name'],
        'businessUnit' => ['business_unit_name'],
        'department' => ['department_name'],
        'unit' => ['unit_name'],
        'subunit' => ['sub_unit_name'],
        'location' => ['location_name'],
        'accountTitle' => ['account_title_name'],
        ];
}
