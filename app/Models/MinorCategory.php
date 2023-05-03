<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MinorCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'major_category_id',
        'minor_category_name',
        'is_active',
    ];
    protected $hidden = [
        'updated_at',
        'created_at',
        'deleted_at'

    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function majorCategory()
    {
        return $this->belongsTo(MajorCategory::class, 'major_category_id', 'id');
    }

    public function masterlists()
    {
        return $this->hasMany(Masterlist::class, 'minor_category_id', 'id');
    }
}
