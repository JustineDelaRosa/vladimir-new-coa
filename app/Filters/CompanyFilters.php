<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class CompanyFilters extends QueryFilters
{
    protected array $allowedFilters = ['company_code', 'company_name'];

    protected array $columnSearch = ['company_code', 'company_name'];
}
