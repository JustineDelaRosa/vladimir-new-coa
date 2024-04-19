<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class DepartmentFilters extends QueryFilters
{
    protected array $allowedFilters = ['department_name', 'department_code'];

    protected array $columnSearch = ['department_name', 'department_code'];

    protected array $relationSearch = [
        'division' => ['division_name'],
        'businessUnit' => ['business_unit_name', 'business_unit_code'],
//        'company' => ['company_name'],
//        'location' => ['location_name'],
    ];
}
