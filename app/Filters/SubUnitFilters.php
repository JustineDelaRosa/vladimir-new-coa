<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class SubUnitFilters extends QueryFilters
{
    protected array $allowedFilters = ['sub_unit_name'];

    protected array $columnSearch = ['sub_unit_name'];

    protected  array $relationSearch = [
        'unit' => ['unit_name', 'unit_code'],
    ];

}
