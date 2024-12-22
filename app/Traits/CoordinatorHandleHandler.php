<?php

namespace App\Traits;

trait CoordinatorHandleHandler
{


    public function indexData($data)
    {
        return [
            'user_id' => $data->first()->user_id,
            'handles' => $data->map(function ($handle) {
                return [
                    'company' => [
                        'id' => $handle->company_id,
                        'code' => $handle->company->company_code,
                        'name' => $handle->company->company_name,
                    ],
                    'business_unit' => [
                        'id' => $handle->business_unit_id,
                        'code' => $handle->businessUnit->business_unit_code,
                        'name' => $handle->businessUnit->business_unit_name,
                    ],
                    'department' => [
                        'id' => $handle->department_id,
                        'code' => $handle->department->department_code,
                        'name' => $handle->department->department_name,
                    ],
                    'unit' => [
                        'id' => $handle->unit_id,
                        'code' => $handle->unit->unit_code,
                        'name' => $handle->unit->unit_name,
                    ],
                    'subunit' => [
                        'id' => $handle->subunit_id,
                        'code' => $handle->subunit->sub_unit_code,
                        'name' => $handle->subunit->sub_unit_name,
                    ],
                    'location' => [
                        'id' => $handle->location_id,
                        'code' => $handle->location->location_code,
                        'name' => $handle->location->location_name,
                    ],
                ];
            }),
        ];
    }
}
