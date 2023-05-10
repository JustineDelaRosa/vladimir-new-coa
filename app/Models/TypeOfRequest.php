<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TypeOfRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'type_of_request_id', 'id');
    }
}
