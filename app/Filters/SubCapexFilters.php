<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class SubCapexFilters extends QueryFilters
{
    protected array $allowedFilters = ['sub_capex', 'sub_project'];

    protected array $columnSearch = ['sub_capex', 'sub_project'];
}
