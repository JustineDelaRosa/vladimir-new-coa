<?php

namespace App\Http\Requests\Status\DepreciationStatus;

use Illuminate\Foundation\Http\FormRequest;

class DepreciationStatusRequest extends FormRequest
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
            return[
                'depreciation_status_name' => 'required|unique:depreciation_statuses,depreciation_status_name',
            ];
        }
        if($this->isMethod('put') && ($this->route()->parameter('depreciation_status'))){
            $id = $this->route()->parameter('depreciation_status');
            return[
                'depreciation_status_name' => 'required|unique:depreciation_statuses,depreciation_status_name,'.$id,
            ];
        }
        if($this->isMethod('patch') && ($this->route()->parameter('id'))){
            return[
                'status' => 'required|boolean',
            ];
        }
    }
}
