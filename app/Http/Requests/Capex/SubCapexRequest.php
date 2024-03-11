<?php

namespace App\Http\Requests\Capex;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubCapexRequest extends FormRequest
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
                'capex_id' => 'required|exists:capexes,id',
                'sub_capex' => ['required', 'max:3'],
                'sub_project' => 'required',
            ];
        }

        if ($this->isMethod('put') && $this->route()->parameter('sub_capex')) {
            $id = $this->route()->parameter('sub_capex');
            return [
                'sub_project' => 'required',
            ];
        }

        if ($this->isMethod('patch') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean'
            ];
        }

    }

    function messages()
    {
        return [
            'capex_id.required' => 'Capex is required',
            'capex_id.exists' => 'Capex does not exists',
            'sub_capex.required' => 'Sub Capex is required',
            'sub_capex.max' => 'Max length of Sub Capex is 3 characters',
            'sub_project.required' => 'Sub Project is required',
        ];
    }
}
