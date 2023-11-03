<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class MovementStatusFilters extends QueryFilters
{
    protected array $allowedFilters = ['movement_status_name'];

    protected array $columnSearch = ['movement_status_name'];
}
