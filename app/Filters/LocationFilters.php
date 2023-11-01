<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class LocationFilters extends QueryFilters
{
    protected array $allowedFilters = ['location_code', 'location_name'];

    protected array $columnSearch = ['location_code', 'location_name'];

    protected array $relationSearch = [
        'departments' => ['department_name']
    ];
}
