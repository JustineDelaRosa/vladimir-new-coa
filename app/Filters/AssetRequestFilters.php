<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetRequestFilters extends QueryFilters
{
    protected array $allowedFilters = [
        'asset_description',
        'asset_specification',
        'accountability',
        'accountable',
        'cellphone_number',
        'brand',
        'status',
    ];

    protected array $columnSearch = [
        'asset_description',
        'asset_specification',
        'accountability',
        'accountable',
        'cellphone_number',
        'brand',
        'status',
    ];

    protected array $relationSearch = [
        'requester' => ['username', 'employee_id', 'firstname', 'lastname'],
        'typeOfRequest' => ['type_of_request_name'],
        'capex' => ['capex'],
        'subCapex' => ['sub_capex'],
    ];
}
