<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class UnitFilters extends QueryFilters
{
    protected array $allowedFilters = ['unit_code', 'unit_name'];

    protected array $columnSearch = ['unit_code', 'unit_name'];

    protected  array $relationSearch =[
        'department' => ['department_code', 'department_name']
    ];
}
