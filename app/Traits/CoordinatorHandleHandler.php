<?php

namespace App\Traits;

trait CoordinatorHandleHandler
{


    public function indexData($data)
    {
        return [
            'user' => [
                'id' => $data->first()->user_id,
                'username' => $data->first()->coordinator->username,
                'employee_id' => $data->first()->coordinator->employee_id,
                'first_name' => $data->first()->coordinator->firstname,
                'last_name' => $data->first()->coordinator->lastname,
                'full_id_number_full_name' => $data->first()->coordinator->employee_id . ' - ' . $data->first()->coordinator->firstname . ' ' . $data->first()->coordinator->lastname,
            ],
            'status' => $data->first()->is_active,
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
            'created_at' => $data->first()->created_at,
        ];
    }
}
