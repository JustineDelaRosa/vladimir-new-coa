<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Filters\SubUnitFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubUnit extends Model
{
    use HasFactory,Filterable,SoftDeletes;

protected string $default_filters = SubUnitFilters::class;

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $guarded = [];


    public function department(){
        return $this->belongsTo(Department::class,'department_id','id');
    }
    public function departmentUnitApprovers(){
        return $this->hasMany(DepartmentUnitApprovers::class,'subunit_id','id');
    }

    public function archive($id)
    {
        $subUnit = self::find($id);
        $subUnit->update(['is_active' => false]);
        $subUnit->delete();
        return $subUnit;
    }
    public function restoreSubUnit($id){
        $subUnit = self::withTrashed()->find($id);
        $subUnit->restore();
        $subUnit->update(['is_active' => true]);
        return $subUnit;
    }

    //TODO: Temporary solution for generating sub unit code
    public function generateCode(){
        //generate sub unit base on the created id, it should be four digit number with leading zero like 0001, 0010, 0100, 1000
        $subUnit = self::latest()->first();
        $subUnitId = $subUnit->id;
        $subUnitId = $subUnitId + 1;
        $subUnitId = str_pad($subUnitId, 4, '0', STR_PAD_LEFT);
        return $subUnitId;
    }
}
