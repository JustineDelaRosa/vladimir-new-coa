<?php

namespace App\Http\Requests\TypeOfRequest;

use Illuminate\Foundation\Http\FormRequest;

class TypeOfRequestRequest extends FormRequest
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
                'type_of_request_name' => 'required|unique:type_of_requests,type_of_request_name',
            ];
        }
        if($this->isMethod('put') && ($this->route()->parameter('type_of_request'))){
            $id = $this->route()->parameter('type_of_request');
            return [
                //unique ignore his own id
                'type_of_request_name' => 'required|unique:type_of_requests,type_of_request_name,'.$id,
            ];
        }

        if($this->isMethod('patch') && ($this->route()->parameter('id'))){
            return [
                'status' => 'required|boolean'
            ];
        }
    }
}
