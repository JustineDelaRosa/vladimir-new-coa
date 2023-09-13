<?php

namespace App\Http\Requests\RoleManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleManagementRequest extends FormRequest
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
        $accessTypes = [
            'masterlist' => ['company', 'department', 'location', 'account-title', 'division', 'type-of-request', 'capex', 'category', 'status-category'],
            'user-management' => ['user-accounts', 'role-management'],
            'settings' => ['approver-settings', 'form-settings']
        ];

        if ($this->isMethod('post')) {

            return [
                'role_name' => 'required|unique:role_management,role_name',
                'access_permission' => ['required', 'array', function ($attribute, $value, $fail) use ($accessTypes) {
                    foreach ($accessTypes as $type => $items) {
                        $this->validateAccessType($type, $items, $value, $fail);
                    }
                }]
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('role_management'))) {
            $id = $this->route()->parameter('role_management');
            return [
                // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL',
                'role_name' => ['required', Rule::unique('role_management', 'role_name')->ignore($id)],
                'access_permission' => ['required', 'array', function ($attribute, $value, $fail) use ($accessTypes) {
                    foreach ($accessTypes as $type => $items) {
                        $this->validateAccessType($type, $items, $value, $fail);
                    }
                }]

            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean',
            ];
        }
    }

    function validateAccessType($type, $items, $inputArray, $fail) {
        if (in_array($type, $inputArray)) {
            $intersect = array_intersect($items, $inputArray);
            if (count($intersect) == 0) {
                $fail('Please select at least one item from the ' . $type);
            }
        }
    }
}
