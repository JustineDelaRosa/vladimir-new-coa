<?php
namespace App\Traits\COA;

trait UnitHandler
{
    public function transformUnit($unit){
        $unit->transform(function ($unit){
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
                'unit_code' => $unit->unit_code,
                'unit_name' => $unit->unit_name,
                'is_active' => $unit->is_active,
                'created_at' => $unit->created_at,
                'updated_at' => $unit->updated_at,
            ];
        });

        return $unit;
    }
}
