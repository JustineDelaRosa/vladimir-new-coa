<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetApprovalFilters extends QueryFilters
{
    protected array $allowedFilters = ['transaction_number', 'approver_id', 'requester_id', 'status', 'layer'];

    protected array $columnSearch = ['transaction_number', 'approver_id', 'requester_id', 'status', 'layer'];

    protected array $relationSearch = [
        'assetRequest' => ['asset_description', 'asset_specification', 'transaction_number'],
        'requester' => ['firstname', 'lastname', 'employee_id'],
    ];
}
