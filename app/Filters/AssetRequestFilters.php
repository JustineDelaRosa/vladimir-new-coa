<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetRequestFilters extends QueryFilters
{
    protected array $allowedFilters = [
        'transaction_number',
        'pr_number'
//        'reference_number',
//        'asset_description',
//        'asset_specification',
//        'accountability',
//        'accountable',
//        'cellphone_number',
//        'brand',
//        'status',
    ];

    protected array $columnSearch = [
        'transaction_number',
        'pr_number'
//        'reference_number',
//        'asset_description',
//        'asset_specification',
//        'accountability',
//        'accountable',
//        'cellphone_number',
//        'brand',
//        'status',
    ];

    protected array $relationSearch = [
        'requestor' => ['username', 'employee_id', 'firstname', 'lastname'],
//        'typeOfRequest' => ['type_of_request_name'],
    ];
}
