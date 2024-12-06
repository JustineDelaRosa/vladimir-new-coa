<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class MovementNumberFilters extends QueryFilters
{
    protected array $allowedFilters = ['status'];

    protected array $columnSearch = ['status'];

    protected array $relationSearch = [
        'requester' => ['firstname', 'lastname', 'employee_id'],
        'transfer.fixedAsset' => ['vladimir_tag_number'],
        'transfer.company' => ['company_code', 'company_name'],
        'transfer.businessUnit' => ['business_unit_code', 'business_unit_name'],
        'transfer.unit' => ['unit_code', 'unit_name'],
        'transfer.department' => ['department_code', 'department_name'],
        'transfer.subUnit' => ['sub_unit_code', 'sub_unit_name'],
        'transfer.receiver' => ['firstname', 'lastname', 'employee_id'],
        'transfer' => ['description'],
        'pullOut' => ['description', 'remarks', 'care_of', 'evaluation'],
        'pullOut.fixedAsset' => ['vladimir_tag_number'],
    ];
}
