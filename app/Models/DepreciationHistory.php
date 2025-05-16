<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepreciationHistory extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id', 'id');

    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }

    public function subUnit()
    {
        return $this->belongsTo(SubUnit::class, 'subunit_id', 'id');
    }

    public function location(){
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    /*public function accountingEntries(){
        return $this->belongsTo(AccountingEntries::class, 'account_id', 'id');
    }*/

    public function depreciationDebit(){
        return $this->belongsTo(AccountTitle::class, 'depreciation_debit_id', 'sync_id');
    }
}
