<?php

namespace App\Models;

use App\Filters\SmallToolFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmallTools extends Model
{
    use HasFactory, Filterable;

    protected string $default_filters = SmallToolFilters::class;
    protected $guarded = [];


    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id', 'sync_id');
    }

    public function item()
    {
        return $this->belongsToMany(
            Item::class,
            "small_tools_item",
            "small_tool_sync_id",
            "item_sync_id",
            "sync_id",
            "sync_id"
        );
    }


}
