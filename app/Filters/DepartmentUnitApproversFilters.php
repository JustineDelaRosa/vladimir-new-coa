<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class DepartmentUnitApproversFilters extends QueryFilters
{
    protected array $allowedFilters = ['department_id, subunit_id, approver_id'];

    protected array $columnSearch = [];

    protected array $relationSearch = [
        'department' => ['department_name', 'department_code'],
        'subUnit' => ['sub_unit_code', 'sub_unit_name'],
        'approver.user' => [
            'username',
            'employee_id',
            'firstname',
            'lastname'
        ],
    ];
}
