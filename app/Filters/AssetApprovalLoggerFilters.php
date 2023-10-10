<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetApprovalLoggerFilters extends QueryFilters
{
    protected array $allowedFilters = [
        'log_name',
        'description',
        'subject_id',
        'subject_type',
        'properties->status',
        'properties->layer',
        'created_at',
    ];

    protected array $columnSearch = [
        'log_name',
        'description',
        'subject_id',
        'subject_type',
        'properties->status',
        'properties->layer',
        'created_at',
    ];

    protected array $relationSearch = [
        'user' => [
            'username',
            'employee_id',
            'firstname',
            'lastname',
        ],
    ];
}
