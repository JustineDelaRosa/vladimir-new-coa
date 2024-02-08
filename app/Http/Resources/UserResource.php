<?php

namespace App\Http\Resources;

use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\FixedAsset;
use App\Models\RoleManagement;
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
            'department_id' => $this->department_id,
            'subunit_id' => $this->subunit_id,
            'is_active' => $this->is_active,
            'role_id' => $this->role_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'role' => $this->role,
        ];
    }
}
