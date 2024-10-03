<?php

namespace App\Http\Requests\ApiTokenRequest;

use Illuminate\Foundation\Http\FormRequest;

class CreateApiTokenRequest extends FormRequest
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
            'token' => 'required|string',
            'p_name' => ['required', 'string', 'max:255', 'unique:api_tokens,p_name']
        ];
    }

    public function messages()
    {
        return [
            'token.required' => 'Token is required',
            'p_name.required' => 'Project name is required',
            'p_name.unique' => 'Project name already exists'
        ];
    }
}
