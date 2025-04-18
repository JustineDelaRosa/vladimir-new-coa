<?php

namespace App\Traits\COA;

trait DepartmentHandler
{
    public function transformDepartment($department, $userId = null)
    {
        $department->transform(function ($department) use ($userId) {
            return [
                'id' => $department->id,
                'sync_id' => $department->sync_id,
                'company' => [
                    'id' => $department->businessUnit->company->id ?? "-",
                    'company_sync_id' => $department->businessUnit->company->sync_id ?? "-",
                    'company_code' => $department->businessUnit->company->company_code ?? "-",
                    'company_name' => $department->businessUnit->company->company_name ?? "-",
                    'company_status' => $department->businessUnit->company->is_active ?? '-',
                ],
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
                'warehouse' => $department->receivingWarehouse,
                'unit' => $this->transformUnits($department->unit(), $userId),
                'department_code' => $department->department_code,
                'department_name' => $department->department_name,
                'is_active' => $department->is_active,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
            ];
        });
        return $department;
    }


    private function transformUnits($unitQuery, $userId)
    {
        // Handle the null userId case
        if ($userId === null) {
            return $unitQuery->get()->map(function ($unit) {
                return $this->formatUnit($unit);
            });
        }

        // Filter and map units when userId is provided
        return $unitQuery->whereHas('coordinatorHandle', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get()->map(function ($unit) {
            return $this->formatUnit($unit);
        });
    }

    private function formatUnit($unit)
    {
        return [
            'id' => $unit->id ?? "-",
            'sync_id' => $unit->sync_id ?? "-",
            'unit_code' => $unit->unit_code ?? "-",
            'unit_name' => $unit->unit_name ?? "-",
            'is_active' => $unit->is_active ?? '-',
        ];
    }
}
