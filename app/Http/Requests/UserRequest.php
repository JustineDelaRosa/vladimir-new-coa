<?php

namespace App\Http\Requests;

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
        return [
            'employee_id' => 'required',
            'first_name' => 'required',
            'middle_name' => 'required',
            'department' => 'required',
            'position' => 'required',
            'username' => 'required|unique:users,username',
            'access_permission' => 'array'
        ];
    }
}
