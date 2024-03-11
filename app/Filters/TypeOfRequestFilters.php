<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class TypeOfRequestFilters extends QueryFilters
{
    protected array $allowedFilters = ['type_of_request_name'];

    protected array $columnSearch = ['type_of_request_name'];
}
