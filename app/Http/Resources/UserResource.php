<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'username' => $this->username,
            'is_coordinator' => $this->is_coordinator,
            'has_handle' =>$this->coordinatorHandle ? 1 : 0,
            'coa' => [
                'one_charging' => $this->oneCharging ?? "-",
                'company' => [
                    'id' => $this->company->id ?? '-',
                    'company_code' => $this->company->company_code ?? '-',
                    'company_name' => $this->company->company_name ?? '-',
                ],
                'business_unit' => [
                    'id' => $this->businessUnit->id ?? '-',
                    'business_unit_code' => $this->businessUnit->business_unit_code ?? '-',
                    'business_unit_name' => $this->businessUnit->business_unit_name ?? '-',
                ],
                'department' => [
                    'id' => $this->department->id ?? '-',
                    'department_code' => $this->department->department_code ?? '-',
                    'department_name' => $this->department->department_name ?? '-',
                    'warehouse' => $this->department->receivingWarehouse ?? '-',
                ],
                'unit' => [
                    'id' => $this->unit->id ?? '-',
                    'unit_code' => $this->unit->unit_code ?? '-',
                    'unit_name' => $this->unit->unit_name ?? '-',
                ],
                'subunit' => [
                    'id' => $this->subunit->id ?? '-',
                    'subunit_code' => $this->subunit->sub_unit_code ?? '-',
                    'subunit_name' => $this->subunit->sub_unit_name ?? '-',
                ],
                'location' => [
                    'id' => $this->location->id ?? '-',
                    'location_code' => $this->location->location_code ?? '-',
                    'location_name' => $this->location->location_name ?? '-',
                ],
            ],
            'is_active' => $this->is_active,
            'role_id' => $this->role_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'role' => $this->role,
        ];
    }
}
