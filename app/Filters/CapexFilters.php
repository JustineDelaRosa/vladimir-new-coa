<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class CapexFilters extends QueryFilters
{
    protected array $allowedFilters = ['capex', 'project_name'];

    protected array $columnSearch = ['capex', 'project_name'];

    protected array $relationSearch = [
        'subCapex' => ['sub_capex', 'sub_project'],
    ];
}
