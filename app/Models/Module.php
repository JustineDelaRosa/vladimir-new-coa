<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'module_name',
        'is_active'
    ];
    protected $guarded = [];

    protected $hidden = [
        'pivot'
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function users() {
        return $this->belongsToMany(User::class, 'access__permissions');
    }
}
