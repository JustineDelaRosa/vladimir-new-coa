<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class MinorCategoryFilters extends QueryFilters
{
    protected array $allowedFilters = ['minor_category_name'];

    protected array $columnSearch = ['minor_category_name'];

    protected array $relationSearch = [
        'majorCategory' => ['major_category_name'],
        'accountTitle' => ['account_title_name'],
    ];
}
