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
        if($this->isMethod('post')){
            $id = $this->route()->parameter('id');
            return [
                //get the capex number from id and check if the sub capex is unique in the capex
                'sub_capex' => ['required', 'max:3'],
                'sub_project' => 'required',
            ];
        }
    }

    function messages()
    {
        return [
            'sub_capex.required' => 'Sub Capex is required',
            'sub_capex.max' => 'Max length of Sub Capex is 3 characters',
            'sub_project.required' => 'Sub Project is required',
        ];
    }
}
