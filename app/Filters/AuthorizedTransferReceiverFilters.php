<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AuthorizedTransferReceiverFilters extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [];

    protected array $relationSearch = [
        'user' => ['firstname', 'lastname', 'employee_id', 'username'],
        'user.company' => ['company_code', 'company_name'],
        'user.businessUnit' => ['business_unit_code', 'business_unit_name'],
        'user.department' => ['department_code', 'department_name'],
        'user.unit' => ['unit_code', 'unit_name'],
        'user.subunit' => ['sub_unit_code', 'sub_unit_name'],
        'user.location' => ['location_code', 'location_name'],
    ];
}
