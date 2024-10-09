<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class ItemFilters extends QueryFilters
{
    protected array $allowedFilters = ['sync_id', 'item_code', 'item_name'];

    protected array $columnSearch = ['sync_id', 'item_code', 'item_name'];
}
