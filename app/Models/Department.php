<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $fillable = [
        'sync_id',
        'department_code',
        'is_active',
        'department_name'
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function masterlists()
    {
        return $this->hasMany(Masterlist::class, 'department_sync_id', 'sync_id');
    }
}
