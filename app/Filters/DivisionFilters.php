<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class DivisionFilters extends QueryFilters
{
    protected array $allowedFilters = ['division_name'];

    protected array $columnSearch = ['division_name'];

    protected array $relationSearch = [
        'department' => ['department_name']
    ];
}
