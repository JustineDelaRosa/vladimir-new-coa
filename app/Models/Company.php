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

    public function masterlists()
    {
        return $this->hasMany(Masterlist::class, 'company_sync_id', 'sync_id');
    }
}
