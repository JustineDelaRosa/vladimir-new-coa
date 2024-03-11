<?php

namespace App\Models\Status;

use App\Filters\CycleCountStatusFilters;
use App\Models\FixedAsset;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CycleCountStatus extends Model
{
    use HasFactory, softDeletes, Filterable;

    protected $guarded = [];

    protected string $default_filters = CycleCountStatusFilters::class;

    public function fixedAssets()
    {
        return $this->hasMany(FixedAsset::class, 'cycle_count_status_id' , 'id');
    }
}
