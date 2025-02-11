<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class ReplacementSmallToolFilters extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = ['status_description'];

    protected array $relationSearch = [
        'item' => ['item_name', 'item_code'],
        'fixedAsset' => ['vladimir_tag_number', 'pr_number', 'po_number', 'serial_number'],
    ];
}
