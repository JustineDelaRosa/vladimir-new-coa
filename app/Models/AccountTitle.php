<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTitle extends Model
{
    use HasFactory;
    protected $fillable = [
        'account_title_code',
        'is_active',
        'account_title_name'
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];
}
