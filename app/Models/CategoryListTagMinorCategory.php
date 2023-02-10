<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryListTagMinorCategory extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'category_list_id',
        'minor_category_id',
        'is_active'
    ];
    protected $hidden = [
        'category_list_id',
        'minor_category_id',
        'updated_at',
        'created_at',
        'deleted_at'
        
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function minorCategory() {
        return $this->belongsTo(MinorCategory::class, 'minor_category_id','id');
    }
}
