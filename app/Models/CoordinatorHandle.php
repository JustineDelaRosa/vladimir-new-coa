<?php

namespace App\Models;

use App\Filters\AuthorizedTransferReceiverFilters;
use App\Filters\CoordinatorHandleFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoordinatorHandle extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];

    protected string $default_filters = CoordinatorHandleFilters::class;


    public function coordinator(){
        return $this->belongsTo(User::class, 'user_id');
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function subunit()
    {
        return $this->belongsTo(SubUnit::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
