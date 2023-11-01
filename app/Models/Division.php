<?php

namespace App\Models;

use App\Filters\DivisionFilters;
use App\Models\MajorCategory;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Division extends Model
{
    use HasFactory,  SoftDeletes, Filterable;

    protected $fillable = [
        'division_name',
        'is_active',
    ];

    protected string $default_filters = DivisionFilters::class;

    public function department()
    {
        return $this->hasMany(Department::class, 'division_id', 'id');
    }
}
