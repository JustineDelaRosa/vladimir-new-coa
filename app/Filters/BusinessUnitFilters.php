<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class BusinessUnitFilters extends QueryFilters
{
    protected array $allowedFilters = ['business_unit_code', 'business_unit_name'];

    protected array $columnSearch = ['business_unit_code', 'business_unit_name'];

    protected array $relationSearch =[
        'company' => ['company_code', 'company_name']
    ];
}
