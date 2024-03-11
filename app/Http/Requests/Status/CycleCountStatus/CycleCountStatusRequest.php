<?php

namespace App\Http\Requests\Status\CycleCountStatus;

use Illuminate\Foundation\Http\FormRequest;

class CycleCountStatusRequest extends FormRequest
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
                'cycle_count_status_name' => 'required|unique:cycle_count_statuses,cycle_count_status_name',
            ];
        }
        if($this->isMethod('put') && ($this->route()->parameter('cycle_count_status'))){
            $id = $this->route()->parameter('cycle_count_status');
            return[
                'cycle_count_status_name' => 'required|unique:cycle_count_statuses,cycle_count_status_name,'.$id,
            ];
        }
        if($this->isMethod('patch') && ($this->route()->parameter('id'))){
            return[
                'status' => 'required|boolean',
            ];
        }
    }
}
