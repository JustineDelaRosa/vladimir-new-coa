<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MajorCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'division_id',
        'major_category_name',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }

    public function minorCategory()
    {
        return $this->hasMany(MinorCategory::class, 'major_category_id', 'id');
    }

    public function masterlists()
    {
        return $this->hasMany(Masterlist::class, 'major_category_id', 'id');
    }
}
