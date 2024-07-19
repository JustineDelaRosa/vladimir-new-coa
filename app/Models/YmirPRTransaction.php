<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YmirPRTransaction extends Model
{
    use HasFactory;

    protected $connection = 'vladimir_ymir_db';
    protected $table = 'pr_transactions';

    protected $guarded = [];
}
