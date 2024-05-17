<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class UnitOfMeasureFilters extends QueryFilters
{
    protected array $allowedFilters = ['uom_code', 'uom_name'];

    protected array $columnSearch = ['uom_code', 'uom_name'];
}
