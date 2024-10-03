<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class ApiTokenFilters extends QueryFilters
{
    protected array $allowedFilters = ['code', 'p_name', 'is_active'];

    protected array $columnSearch = ['code', 'p_name', 'is_active'];
}
