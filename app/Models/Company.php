<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    // protected $fillable = [
    //     'sync_id',
    //     'company_code',
    //     'is_active',
    //     'company_name'
    // ];
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'company_sync_id', 'sync_id');
    }

    public function department()
    {
        return $this->hasMany(Department::class, 'company_sync_id', 'sync_id');
    }

}
