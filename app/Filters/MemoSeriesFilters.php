<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class MemoSeriesFilters extends QueryFilters
{
    protected array $allowedFilters = [
        'id',
        'memo_series',
    ];

    protected array $columnSearch = [
        'id',
        'memo_series',
    ];

    protected array $relationSearch = [
        'fixedAssets' => [
            'vladimir_tag_number',
            'tag_number',
            'tag_number_old',
            'asset_description',
            'accountability',
            'accountable',
            'brand',
            'depreciation_method',
        ],
        'fixedAssets.company' => [
            'company_code',
            'company_name',
        ],
        'fixedAssets.businessUnit' => [
            'business_unit_code',
            'business_unit_name',
        ],
        'fixedAssets.department' => [
            'department_code',
            'department_name',
        ],
        'fixedAssets.unit' => [
            'unit_code',
            'unit_name',
        ],
        'fixedAssets.subunit' => [
            'sub_unit_code',
            'sub_unit_name',
        ],
        'fixedAssets.location' => [
            'location_code',
            'location_name',
        ],
        'fixedAssets.accountTitle' => [
            'account_title_code',
            'account_title_name',
        ],
    ];
}
