<?php

namespace App\Http\Requests\ApiTokenRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiTokenRequest extends FormRequest
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
            'endpoint' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'token.required' => 'Token is required',
            'endpoint.required' => 'Endpoint is required',
            'endpoint.string' => 'Provide a valid endpoint',
        ];
    }
}
