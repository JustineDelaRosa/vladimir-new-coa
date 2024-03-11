<?php

namespace App\Http\Requests\Status\AssetStatus;

use Illuminate\Foundation\Http\FormRequest;

class AssetStatusRequest extends FormRequest
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
                'asset_status_name' => 'required|unique:asset_statuses,asset_status_name',
            ];
        }

        if($this->isMethod('put') && ($this->route()->parameter('asset_status'))){
            $id = $this->route()->parameter('asset_status');
            return[
                'asset_status_name' => 'required|unique:asset_statuses,asset_status_name,'.$id,
            ];
        }

        if($this->isMethod('patch') && ($this->route()->parameter('id'))){
            return[
                'status' => 'required|boolean',
            ];
        }
    }
}
