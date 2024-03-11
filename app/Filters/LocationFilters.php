<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class LocationFilters extends QueryFilters
{
    protected array $allowedFilters = ['location_code', 'location_name'];

    protected array $columnSearch = ['location_code', 'location_name'];

    protected array $relationSearch = [
        'subunit' => ['sub_unit_name', 'sub_unit_code']
    ];
}
