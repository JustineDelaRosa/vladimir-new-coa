<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AccountTitleFilters extends QueryFilters
{
    protected array $allowedFilters = ['account_tittle_code', 'account_title_name'];

    protected array $columnSearch = ['account_title_code', 'account_title_name'];
}
