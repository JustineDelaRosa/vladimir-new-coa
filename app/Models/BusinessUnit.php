<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessUnit extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_sync_id', 'sync_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'business_unit_sync_id', 'sync_id');
    }
}
