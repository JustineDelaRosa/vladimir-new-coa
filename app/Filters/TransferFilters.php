<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class TransferFilters extends QueryFilters
{
    protected array $allowedFilters = ['accountable', 'description'];

    protected array $columnSearch = ['accountable', 'description'];

    protected array $relationSearch = [
        'fixedAsset' => ['vladimir_tag_number'],
        'company' => ['company_code', 'company_name'],
        'businessUnit' => ['business_unit_code', 'business_unit_name'],
        'unit' => ['unit_code', 'unit_name'],
        'department' => ['department_code', 'department_name'],
        'subUnit' => ['subunit_code', 'subunit_name'],
        'movementNumber' => ['movement_number'],
        'receiver' => ['firstname', 'lastname', 'employee_id'],
    ];
}
