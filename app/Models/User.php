<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'firstname',
        'lastname',
        'username',
        'password',
        'is_active',
        'role_id',
    ];

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


    // public function modules() {
    //     return $this->belongsToMany(Module::class, 'access__permissions');
    // }

    // public function department(){
    //     return $this->belongsTo(Department::class)->select('id', 'department_name');
    // }

    public function role(){
        return $this->belongsTo(RoleManagement::class, 'role_id','id');
    }

    public function requester(){
        return $this->hasMany(UserApprover::class, 'requester_id', 'id');
    }

    public function approver(){
        return $this->hasMany(UserApprover::class, 'requester_id', 'id');
    }

//    public function requestor(){
//        return $this->hasMany(ApproverLayer::class, 'user_id', 'id');
//    }
//    public function layer1(){
//        return $this->hasMany(ApproverLayer::class, 'layer1', 'id');
//    }
//    public function layer2(){
//        return $this->hasMany(ApproverLayer::class, 'layer2', 'id');
//    }
//    public function layer3(){
//        return $this->hasMany(ApproverLayer::class, 'layer3', 'id');
//    }
//    public function layer4(){
//        return $this->hasMany(ApproverLayer::class, 'layer4', 'id');
//    }
//    public function layer5(){
//        return $this->hasMany(ApproverLayer::class, 'layer5', 'id');
//    }
}
