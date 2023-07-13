<?php

namespace App\Http\Requests\Status\MovementStatus;

use Illuminate\Foundation\Http\FormRequest;

class MovementStatusRequest extends FormRequest
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
                'movement_status_name' => 'required|unique:movement_statuses,movement_status_name',
            ];
        }
        if($this->isMethod('put') && ($this->route()->parameter('movement_status'))){
            $id = $this->route()->parameter('movement_status');
            return[
                'movement_status_name' => 'required|unique:movement_statuses,movement_status_name,'.$id,
            ];
        }

        if($this->isMethod('patch') && ($this->route()->parameter('id'))){
            return[
                'status' => 'required|boolean',
            ];
        }
    }
}
