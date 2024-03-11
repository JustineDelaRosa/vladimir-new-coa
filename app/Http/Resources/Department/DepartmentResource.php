<?php

namespace App\Http\Resources\Department;

use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' =>$this->id,
            'sync_id' =>$this->sync_id,
            'company' =>[
                'id' =>$this->company->id,
                'sync_id' =>$this->company->sync_id,
                'company_code' =>$this->company->company_code,
                'company_name' =>$this->company->company_name,
            ],
            'department_code' =>$this->department_code,
            'department_name' =>$this->department_name,
            'is_active' =>$this->is_active,
            'created_at' =>$this->created_at,
            'updated_at' =>$this->updated_at,

        ];
    }
}
