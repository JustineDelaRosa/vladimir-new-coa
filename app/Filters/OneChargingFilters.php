<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class OneChargingFilters extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [
        'code',
        'name',
        'company_code',
        'company_name',
        'business_unit_code',
        'business_unit_name',
        'department_code',
        'department_name',
        'unit_code',
        'unit_name',
        'subunit_code',
        'subunit_name',
        'location_code',
        'location_name',
    ];
}
