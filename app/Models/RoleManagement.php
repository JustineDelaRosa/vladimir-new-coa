<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class RoleManagement extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'role_name',
        'access_permission',
        'is_active'
    ];

    protected $casts = [
        'access_permission'=>'json',
    ];
}
