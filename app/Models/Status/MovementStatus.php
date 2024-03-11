<?php

namespace App\Models\Status;

use App\Filters\MovementStatusFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovementStatus extends Model
{
    use HasFactory, softDeletes, Filterable;

    protected $guarded = [];

    protected string $default_filters = MovementStatusFilters::class;

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'movement_status_id', 'id');
    }
}
