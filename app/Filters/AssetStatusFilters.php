<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetStatusFilters extends QueryFilters
{
    protected array $allowedFilters = ['asset_status_name'];

    protected array $columnSearch = ['asset_status_name'];
}
