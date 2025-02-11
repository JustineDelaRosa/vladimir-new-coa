<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class CreditFilters extends QueryFilters
{
    protected array $allowedFilters = ['credit_code', 'credit_name'];

    protected array $columnSearch = ['credit_code', 'credit_name'];
}
