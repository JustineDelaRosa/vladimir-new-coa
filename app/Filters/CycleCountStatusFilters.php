<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class CycleCountStatusFilters extends QueryFilters
{
    protected array $allowedFilters = ['cycle_count_status_name'];

    protected array $columnSearch = ['cycle_count_status_name'];
}
