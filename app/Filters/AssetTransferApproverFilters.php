<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetTransferApproverFilters extends QueryFilters
{
    protected array $allowedFilters = ['transfer_number'];

    protected array $columnSearch = ['transfer_number'];

    protected array $relationSearch = [
        'transferRequest' => [
            'description',
            'status',
        ],
    ];
}
