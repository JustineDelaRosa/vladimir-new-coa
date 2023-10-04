<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetApprovalFilters extends QueryFilters
{
    protected array $allowedFilters = ['asset_request_id', 'approver_id', 'requester_id', 'status', 'layer'];

    protected array $columnSearch = ['asset_request_id', 'approver_id', 'requester_id', 'status', 'layer'];

    protected array $relationSearch = [
        'assetRequest' => ['asset_description', 'asset_specification'],
        'requester' => ['firstname', 'lastname', 'employee_id'],
    ];
}
