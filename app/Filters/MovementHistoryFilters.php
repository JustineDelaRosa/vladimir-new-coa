<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class MovementHistoryFilters extends QueryFilters
{
    protected array $allowedFilters = ['vladimir_tag_number', 'asset_description'];

    protected array $columnSearch = ['vladimir_tag_number', 'asset_description'];
}
