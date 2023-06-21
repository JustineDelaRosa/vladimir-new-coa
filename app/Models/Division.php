<?php

namespace App\Models;

use App\Models\MajorCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Division extends Model
{
    use HasFactory,  SoftDeletes;

    protected $fillable = [
        'division_name',
        'is_active',
    ];

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'division_id', 'id');
    }
}
