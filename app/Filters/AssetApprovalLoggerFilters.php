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
        'causer_id',
        'causer_type',
        'properties->status',
        'properties->layer',
        'properties->approver->firstname',
        'properties->approver->lastname',
        'properties->approver->employee_id',
        'created_at',
        'updated_at'];

    protected array $columnSearch = [
        'log_name',
        'description',
        'subject_id',
        'subject_type',
        'causer_id',
        'causer_type',
        'properties->status',
        'properties->layer',
        'properties->approver->firstname',
        'properties->approver->lastname',
        'properties->approver->employee_id',
        'created_at',
        'updated_at',
    ];


}
