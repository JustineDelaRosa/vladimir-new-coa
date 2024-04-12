<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetPullOutApproverFilters extends QueryFilters
{
    protected array $allowedFilters = ['department_id, subunit_id, approver_id'];

    protected array $columnSearch = [];

    protected array $relationSearch = [
        'unit' => ['unit_name', 'unit_code'],
        'subUnit' => ['sub_unit_code', 'sub_unit_name'],
        'approver.user' => [
            'username',
            'employee_id',
            'firstname',
            'lastname'
        ],
    ];
}
