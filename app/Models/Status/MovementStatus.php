<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovementStatus extends Model
{
    use HasFactory, softDeletes;

    protected $guarded = [];

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'movement_status_id', 'id');
    }
}
