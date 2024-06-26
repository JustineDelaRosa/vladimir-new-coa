<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class AssetTransferRequestFilters extends QueryFilters
{
    protected array $allowedFilters = [
        'transfer_number',
        'description',
    ];

    protected array $columnSearch = [
        'transfer_number',
        'description',
    ];
}
