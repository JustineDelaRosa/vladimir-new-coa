<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class UserFilters extends QueryFilters
{
    protected array $allowedFilters = ['employee_id', 'firstname', 'lastname', 'username'];

    protected array $columnSearch = ['employee_id', 'firstname', 'lastname', 'username'];

    protected  array $relationSearch = [
        'company' => ['company_name', 'company_code'],
        'businessUnit' => ['business_unit_name', 'business_unit_code'],
        'department' => ['department_name', 'department_code'],
        'unit' => ['unit_name', 'unit_code'],
        'subUnit' => ['sub_unit_name', 'sub_unit_code'],
        'location' => ['location_name', 'location_code'],
        'role' => ['role_name'],
    ];
}
