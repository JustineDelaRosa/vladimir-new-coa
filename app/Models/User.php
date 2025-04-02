<?php

namespace App\Models;

use App\Filters\UserFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, Filterable;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // 'password',
        // 'remember_token',
        // 'pivot',
        // 'department_id'
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'status' => 'boolean',
    ];

    protected string $default_filters = UserFilters::class;
    // public function modules() {
    //     return $this->belongsToMany(Module::class, 'access__permissions');
    // }

    // public function department(){
    //     return $this->belongsTo(Department::class)->select('id', 'department_name');
    // }

    public function authorizedTransferReceiver()
    {
        return $this->hasMany(AuthorizedTransferReceiver::class, 'user_id', 'id');
    }
    public function coordinatorHandle()
    {
        return $this->hasOne(CoordinatorHandle::class, 'user_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }
    public function subunit()
    {
        return $this->belongsTo(SubUnit::class, 'subunit_id', 'id');
    }
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }
    public function role()
    {
        return $this->belongsTo(RoleManagement::class, 'role_id', 'id');
    }

    public function requester()
    {
        return $this->hasMany(UserApprover::class, 'requester_id', 'id');
    }

    public function approvers()
    {
        return $this->hasMany(Approvers::class, 'approver_id', 'id');
    }

    public function assetRequest()
    {
        return $this->hasMany(AssetRequest::class, 'requester_id', 'id');
    }

    public function assetApproval()
    {
        return $this->hasMany(AssetApproval::class, 'requester_id', 'id');
    }
    public function roleManagement()
    {
        return $this->belongsTo(RoleManagement::class, 'role_id', 'id');
    }
    public function warehouse(){
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'sync_id');
    }
}
