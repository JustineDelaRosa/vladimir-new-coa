<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Access_Permission extends Model
{
    use HasFactory;
    protected $fillable = [
        'module_id',
        'user_id'
    ];
    protected $guarded = [];
 
}
