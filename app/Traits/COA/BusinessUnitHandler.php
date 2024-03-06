<?php
namespace App\Traits\COA;

trait BusinessUnitHandler
{
    public function transformBusinessUnit($businessUnit){
        $businessUnit->transform(function($businessUnit){
            return [
                'id' => $businessUnit->id,
                'sync_id' => $businessUnit->sync_id,
                'code' => $businessUnit->business_unit_code,
                'name' => $businessUnit->business_unit_name,
                'is_active' => $businessUnit->is_active,
                'company' => $businessUnit->company_sync_id,
            ];
        });
        return $businessUnit;
    }
}
