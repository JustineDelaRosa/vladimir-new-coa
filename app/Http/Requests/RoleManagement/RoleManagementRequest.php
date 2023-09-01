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
        if ($this->isMethod('post')) {

            $masterlist_arr = [
                'company', 'department', 'location', 'account-title', 'division', 'type-of-request', 'capex', 'category', 'status-category'
            ];
            $user_management_arr = [
                'user-accounts', 'role-management'
            ];

            return [
                'role_name' => 'required|unique:role_management,role_name',
                'access_permission' => ['required', 'array', function ($attribute, $value, $fail) use ($masterlist_arr, $user_management_arr) {
                    //check the array if it has masterlist in it then atleast one of the masterlist should be selected
                    if (in_array('masterlist', $value)) {
                        $masterlist = array_intersect($masterlist_arr, $value);
                        if (count($masterlist) == 0) {
                            $fail('Atleast one of the masterlist should be selected');
                        }
                    }
                    //check the array if it has user-management in it then atleast one of the user-management should be selected
                    if (in_array('user-management', $value)) {
                        $user_management = array_intersect($user_management_arr, $value);
                        if (count($user_management) == 0) {
                            $fail('Atleast one of the user management should be selected');
                        }
                    }
                }]
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('role_management'))) {
            $id = $this->route()->parameter('role_management');
            return [
                // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL',
                'role_name' => ['required', Rule::unique('role_management', 'role_name')->ignore($id)],
                'access_permission' => 'required|array'

            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean',

            ];
        }
    }
}
