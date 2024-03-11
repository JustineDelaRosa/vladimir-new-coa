<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class MajorCategoryFilters extends QueryFilters
{
    protected array $allowedFilters = ['major_category_name'];

    protected array $columnSearch = ['major_category_name'];
}
