<?php

namespace App\Http\Requests;

use App\Models\SubUnit;
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
        if ($this->isMethod('post')) {

            return [
                'employee_id' => 'required|unique:users,employee_id',
                'firstname' => 'required',
                'lastname' => 'required',
                'username' => 'required|unique:users,username',
                'department_id' => ['required', 'exists:departments,id'],
                'subunit_id' => ['bail','required', 'exists:sub_units,id',
                    function ($attribute, $value, $fail) {
                    if (!$this->checkSubunitOnDepartment($this->department_id, $value)) {
                        $fail('Invalid subunit for this department');
                    }
                }],
                'role_id' => 'required|exists:role_management,id',
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('user'))) {
            $id = $this->route()->parameter('user');
            return [
                // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL',
                'username' => ['required', Rule::unique('users', 'username')->ignore($id)],
                'role_id' => 'required|exists:role_management,id',
                'department_id' => ['required', 'exists:departments,id'],
                'subunit_id' => ['bail','required',  'exists:sub_units,id',
                    function ($attribute, $value, $fail) {
                    if (!$this->checkSubunitOnDepartment($this->department_id, $value)) {
                        $fail('Invalid subunit for this department');
                    }
                }],
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
