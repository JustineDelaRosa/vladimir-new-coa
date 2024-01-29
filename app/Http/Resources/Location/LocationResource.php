<?php

namespace App\Http\Resources\Location;

use App\Models\Company;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'location' =>[
                'id' =>$this->id,
                'sync_id' =>$this->sync_id,
                'location_code' =>$this->location_code,
                'location_name' =>$this->location_name,
                'is_active' =>$this->is_active,
                'departments' => $this->locationDepartment->map(function ($department) {
                    return [
                        'id' => $department->id,
                        'sync_id' => $department->sync_id,
                        'company_id' => $department->company_id,
                        'department_code' => $department->department_code,
                        'department_name' => $department->department_name,
                        'is_active' => $department->is_active,
                        'created_at' => $department->created_at,
                        'updated_at' => $department->updated_at,
                    ];
                }),
            ]
        ];
    }
}
