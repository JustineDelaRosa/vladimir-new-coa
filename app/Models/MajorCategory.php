<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MajorCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'major_category_name',
        'is_active',
        'classification'
    ];
}
