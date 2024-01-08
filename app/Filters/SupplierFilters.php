<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class SupplierFilters extends QueryFilters
{
    protected array $allowedFilters = ['supplier_name', 'supplier_code', 'is_active'];

    protected array $columnSearch = ['supplier_name', 'supplier_code'];
}
