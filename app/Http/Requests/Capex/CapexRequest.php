<?php

namespace App\Http\Requests\Capex;

use Illuminate\Foundation\Http\FormRequest;

class CapexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {

        if ($this->isMethod('post')) {
            return [
                //do not allow letters and special characters except dash
                'capex' => 'required|unique:capexes,capex|regex:/^[0-9-]+$/',
                'project_name' => 'required',
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('capex'))) {
            $id = $this->route()->parameter('capex');
            return [
                //unique ignore his own id
                'project_name' => 'required',
            ];
        }

        if ($this->isMethod('patch') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean'
            ];
        }
    }

    public function messages(): array
    {
        return [
            'capex.required' => 'Capex is required.',
            'capex.unique' => 'Capex already exists.',
            'capex.regex' => 'Capex should not have a letter.',
            'project_name.required' => 'Project name is required.',
            'status.required' => 'Status is required.',
            'status.boolean' => 'Status should be boolean.',
        ];
    }
}
