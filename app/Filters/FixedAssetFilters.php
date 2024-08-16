<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class FixedAssetFilters extends QueryFilters
{
    protected array $allowedFilters = [
        'vladimir_tag_number',
        'fixed_assets.tag_number',
        'fixed_assets.tag_number_old',
        'fixed_assets.asset_description',
        'fixed_assets.accountability',
        'fixed_assets.accountable',
        'fixed_assets.brand',
        'fixed_assets.depreciation_method',
        'fixed_assets.transaction_number',
        'fixed_assets.reference_number',
        'fixed_assets.receipt',
    ];

    protected array $columnSearch = [
        'vladimir_tag_number',
        'fixed_assets.tag_number',
        'fixed_assets.tag_number_old',
        'fixed_assets.asset_description',
        'fixed_assets.accountability',
        'fixed_assets.accountable',
        'fixed_assets.brand',
        'fixed_assets.depreciation_method',
        'fixed_assets.transaction_number',
        'fixed_assets.reference_number',
        'fixed_assets.receipt',
    ];

    protected array $relationSearch = [
//        'subCapex' =>['sub_capex', 'sub_project'],
//        'majorCategory' => ['major_category_name'],
//        'minorCategory' => ['minor_category_name'],
        'department.division' => ['division_name'],
//        'assetStatus' => ['asset_status_name'],
//        'cycleCountStatus' => ['cycle_count_status_name'],
//        'depreciationStatus' => ['depreciation_status_name'],
//        'movementStatus' => ['movement_status_name'],
        'company' => ['company_code','company_name'],
        'businessUnit' => ['business_unit_code','business_unit_name'],
        'department' => ['department_code','department_name'],
        'unit' => ['unit_code','unit_name'],
        'subunit' => ['sub_unit_code','sub_unit_name'],
        'location' => ['location_code','location_name'],
        'accountTitle.initialCredit' => ['account_title_code','account_title_name'],
    ];
}
