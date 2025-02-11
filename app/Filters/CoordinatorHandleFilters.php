<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class CoordinatorHandleFilters extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [];

    protected array $relationSearch = [
        'coordinator' => ['firstname', 'lastname', 'employee_id', 'username'],
        'company' => ['company_code', 'company_name'],
        'businessUnit' => ['business_unit_code', 'business_unit_name'],
        'department' => ['department_code', 'department_name'],
        'unit' => ['unit_code', 'unit_name'],
        'subunit' => ['sub_unit_code', 'sub_unit_name'],
        'location' => ['location_code', 'location_name'],
    ];
}
