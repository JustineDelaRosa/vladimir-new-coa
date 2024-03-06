<?php
namespace App\Traits\COA;

trait SubUnitHandler
{
    public function transformSubunit($subUnits){
        $subUnits->transform(function ($subUnit) {
            return [
                'id' => $subUnit->id,
                'subunit_code' => $subUnit->sub_unit_code ?? '-',
                'subunit_name' => $subUnit->sub_unit_name,
                'is_active' => $subUnit->is_active,
                'unit' => [
                    'id' => $subUnit->unit->id,
                    'unit_code' => $subUnit->unit->unit_code,
                    'unit_name' => $subUnit->unit->unit_name,
                ],
                'tagged' => $subUnit->departmentUnitApprovers()->exists(),
            ];
        });

        return $subUnits;
    }
}
