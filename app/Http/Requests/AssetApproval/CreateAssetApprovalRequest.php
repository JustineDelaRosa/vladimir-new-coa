<?php

namespace App\Http\Requests\AssetApproval;

use Illuminate\Foundation\Http\FormRequest;

class CreateAssetApprovalRequest extends FormRequest
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
        if ($this->isMethod('PATCH')) {
            return [
                'asset_approval_id' => 'required|exists:asset_approvals,id|array',
            ];
        }
    }

    function messages()
    {
        return [
            'asset_approval_id.required' => 'The asset approval id is required',
            'asset_approval_id.exists' => 'The asset approval id must be an existing id',
            'asset_approval_id.array' => 'The asset approval id must be an array',
        ];
    }
}
