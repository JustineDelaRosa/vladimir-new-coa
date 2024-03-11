<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class RoleManagementFilters extends QueryFilters
{
    protected array $allowedFilters = ['role_name', 'access_permission'];

    protected array $columnSearch = ['role_name', 'access_permission'];
}
