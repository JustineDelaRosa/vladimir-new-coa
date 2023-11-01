<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class PrinterIPFilters extends QueryFilters
{
    protected array $allowedFilters = ['ip', 'name'];

    protected array $columnSearch = ['ip', 'name'];
}
