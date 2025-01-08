<?php
namespace App\Traits\COA;

trait UnitHandler
{
    public function transformUnit($unit, $userId = null){
        $unit->transform(function ($unit) use ($userId){
            return[
                'id' => $unit->id,
                'sync_id' => $unit->sync_id,
                'department' => [
                    'id' => $unit->departments->id ?? "-",
                    'department_sync_id' => $unit->departments->sync_id ?? "-",
                    'department_code' => $unit->departments->department_code ?? "-",
                    'department_name' => $unit->departments->department_name ?? "-",
                    'department_status' => $unit->departments->is_active ?? '-',
                ],
                'subunit' =>$this->transformSubUnits($unit->subunits(), $userId),
                'unit_code' => $unit->unit_code,
                'unit_name' => $unit->unit_name,
                'is_active' => $unit->is_active,
                'created_at' => $unit->created_at,
                'updated_at' => $unit->updated_at,
            ];
        });

        return $unit;
    }




    private function transformSubUnits($subUnitQuery, $userId)
    {
        // Handle the null userId case
        if ($userId === null) {
            return $subUnitQuery->get()->map(function ($subUnit) {
                return $this->formatSubUnit($subUnit);
            });
        }

        // Filter and map units when userId is provided
        return $subUnitQuery->whereHas('coordinatorHandle', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get()->map(function ($subUnit) {
            return $this->formatSubUnit($subUnit);
        });
    }

    private function formatSubUnit($subUnit)
    {
        return [
            'id' => $subUnit->id ?? "-",
            'sync_id' => $subUnit->sync_id ?? "-",
            'subunit_code' => $subUnit->sub_unit_code ?? "-",
            'subunit_name' => $subUnit->sub_unit_name ?? "-",
            'is_active' => $subUnit->is_active ?? '-',
        ];
    }
}
