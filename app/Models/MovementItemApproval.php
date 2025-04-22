<?php

namespace App\Models;

use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovementItemApproval extends Model
{
    use HasFactory, Filterable;

    protected $guarded = [];

    public function movementItem()
    {
        return $this->morphTo('movementItem', 'item_type', 'item_id');
    }
}
