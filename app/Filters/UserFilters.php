<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class UserFilters extends QueryFilters
{
    protected array $allowedFilters = ['employee_id', 'firstname', 'lastname', 'username'];

    protected array $columnSearch = ['employee_id', 'firstname', 'lastname', 'username'];

    protected  array $relationSearch = [
        'department' => ['department_name'],
        'subUnit' => ['sub_unit_name'],
        'role' => ['role_name'],
    ];
}
