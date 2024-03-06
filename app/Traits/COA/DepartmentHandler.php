<?php
namespace App\Traits\COA;

trait DepartmentHandler
{
    public function transformDepartment($department){
        $department->transform(function($department){
            return [
                'id' => $department->id,
                'sync_id' => $department->sync_id,
                'business_unit' => [
                    'id' => $department->businessUnit->id ?? "-",
                    'business_unit_sync_id' => $department->businessUnit->sync_id ?? "-",
                    'business_unit_code' => $department->businessUnit->business_unit_code ?? "-",
                    'business_unit_name' => $department->businessUnit->business_unit_name ?? "-",
                    'business_unit_status' => $department->businessUnit->is_active ?? '-',
                ],
                'division' => [
                    'division_id' => $department->division->id ?? "-",
                    'division_name' => $department->division->division_name ?? "-",
                ],
                'department_code' => $department->department_code,
                'department_name' => $department->department_name,
                'is_active' => $department->is_active,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
            ];
        });
        return $department;
    }
}
