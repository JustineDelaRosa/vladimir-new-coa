<?php

namespace App\Models;

use App\Filters\RoleManagementFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class RoleManagement extends Model
{
    use HasFactory, SoftDeletes, Filterable;
    protected $fillable = [
        'role_name',
        'access_permission',
        'is_active'
    ];

    protected $casts = [
        'access_permission'=>'json',
    ];

    protected string $default_filters = RoleManagementFilters::class;
}
