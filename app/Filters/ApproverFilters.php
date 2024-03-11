<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class ApproverFilters extends QueryFilters
{
    protected array $allowedFilters = ['approver_id'];

    protected array $columnSearch = ['approver_id'];

    protected array $relationSearch = [
        'user' => [
            'username',
            'employee_id',
            'firstname',
            'lastname',
        ],
    ];
}
