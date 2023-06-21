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
            return [
              'capex' => 'required|unique:capexes,capex',
                'project_name' => 'required|unique:capexes,project_name',
            ];
        }
        if($this->isMethod('put') && ($this->route()->parameter('capex'))){
            $id = $this->route()->parameter('capex');
            return [
                //unique ignore his own id
                'capex' => 'required|unique:capexes,capex,'.$id,
                'project_name' => 'required|unique:capexes,project_name,'.$id,
            ];
        }

        if($this->isMethod('patch') && ($this->route()->parameter('id'))){
            return [
                'status' => 'required|boolean'
            ];
        }
    }
}
