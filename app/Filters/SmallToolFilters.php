<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class SmallToolFilters extends QueryFilters
{
    protected array $allowedFilters = ['sync_id', 'small_tool_code', 'small_tool_name'];

    protected array $columnSearch = ['sync_id', 'small_tool_code', 'small_tool_name'];
}
