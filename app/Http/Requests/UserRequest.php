<?php

namespace App\Http\Requests;

use App\Models\RoleManagement;
use App\Models\SubUnit;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $roleName = RoleManagement::where('id', request()->role_id)->value('role_name');
        if ($this->isMethod('post')) {
//                $roleName = RoleManagement::where('id', request()->role_id)->value('role_name');
            return [
                'employee_id' => 'required|unique:users,employee_id',
                'firstname' => 'required',
                'lastname' => 'required',
                'username' => 'required|unique:users,username',
                'is_coordinator' => 'nullable|boolean',
                'company_id' => 'required|exists:companies,id',
                'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
                'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
                'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
                'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, false)],
                'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
                'role_id' => 'required|exists:role_management,id',
                'warehouse_id' => $roleName === 'Warehouse' ? 'required|exists:warehouses,id' : 'nullable|exists:warehouses,id',
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('user'))) {
            $id = $this->route()->parameter('user');
            return [
                // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL',
                'username' => ['required', Rule::unique('users', 'username')->ignore($id)],
                'role_id' => 'required|exists:role_management,id',
                'company_id' => 'required|exists:companies,id',
                'is_coordinator' => 'nullable|boolean',
                'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
                'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
                'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
                'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, false)],
                'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
                'warehouse_id' => $roleName === 'Warehouse' ? 'required|exists:warehouses,id' : 'nullable|exists:warehouses,id',
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean',
                // 'id' => 'exists:major_categories,id',
            ];
        }
    }

    private function checkSubunitOnDepartment($department_id, $subunit_id): bool
    {
        $subunit = SubUnit::where('department_id', $department_id)->where('id', $subunit_id)->first();
        if ($subunit) {
            return true;
        }
        return false;
    }
}
