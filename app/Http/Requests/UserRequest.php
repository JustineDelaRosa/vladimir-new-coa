<?php

namespace App\Http\Requests;

use App\Rules\FormatAndCheckDepartment;
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
                'department_name' => ['required'], //,new FormatAndCheckDepartment
                'subunit_name' => 'required',
                'role_id' => 'required|exists:role_management,id',
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('user'))) {
            $id = $this->route()->parameter('user');
            return [
                // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL',
                'username' => ['required', Rule::unique('users', 'username')->ignore($id)],
                'role_id' => 'required|exists:role_management,id',
                'department_name' => ['required'],//new FormatAndCheckDepartment
                'subunit_name' => 'required'

            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean',
                // 'id' => 'exists:major_categories,id',
            ];
        }
    }
}
