<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryList extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'service_provider_id',
        'major_category_id',
        'minor_category_id',
        'is_active'
    ];

    protected $hidden = [
        'service_provider_id',
        'major_category_id',
        'minor_category_id',
        'updated_at',
        'created_at',
        'deleted_at'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    


    public function serviceProvider() {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id','id');
    }

    public function majorCategory() {
        return $this->belongsTo(MajorCategory::class, 'major_category_id','id');
    }

    public function categoryListTag() {
        return $this->hasMany(CategoryListTagMinorCategory::class, 'category_list_id','id');
    }
}

