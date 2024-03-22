<?php
namespace App\Traits\COA;

trait BusinessUnitHandler
{
    public function transformBusinessUnit($businessUnit){
        $businessUnit->transform(function($businessUnit){
            return [
                'id' => $businessUnit->id,
                'sync_id' => $businessUnit->sync_id,
                'business_unit_code' => $businessUnit->business_unit_code,
                'business_unit_name' => $businessUnit->business_unit_name,
                'is_active' => $businessUnit->is_active,
                'company' => [
                    'id' => $businessUnit->company->id ?? "-",
                    'company_sync_id' => $businessUnit->company->sync_id ?? "-",
                    'company_code' => $businessUnit->company->company_code ?? "-",
                    'company_name' => $businessUnit->company->company_name ?? "-",
                    'company_status' => $businessUnit->company->is_active ?? '-',
                ],
            ];
        });
        return $businessUnit;
    }
}
