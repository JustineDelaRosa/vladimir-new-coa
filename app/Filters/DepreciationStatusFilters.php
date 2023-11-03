<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class DepreciationStatusFilters extends QueryFilters
{
    protected array $allowedFilters = ['depreciation_status_name'];

    protected array $columnSearch = ['depreciation_status_name'];
}
