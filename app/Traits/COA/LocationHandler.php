<?php
namespace App\Traits\COA;

trait LocationHandler
{
    public function transformLocation($location){
        $location->transform(function ($location) {
            return  [
                'id' => $location->id,
                'sync_id' => $location->sync_id,
                'location_code' => $location->location_code,
                'location_name' => $location->location_name,
//                'warehouse' => $location->receivingWarehouse,
                'subunit' => $location->subunit->map(function ($subunit) {
                    return [
                        'id' => $subunit->id ?? '-',
                        'sync_id' => $subunit->sync_id ?? '-',
                        'subunit_code' => $subunit->sub_unit_code ?? '-',
                        'subunit_name' => $subunit->sub_unit_name ?? '-',
                        'subunit_status' => (bool)$subunit->is_active,
                    ];
                }),
                'is_active' => $location->is_active,
                'created_at' => $location->created_at,
                'updated_at' => $location->updated_at,
            ];
        });

        return $location;
    }
}
