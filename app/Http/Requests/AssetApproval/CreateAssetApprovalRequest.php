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
                'action' => 'required|string|in:Approved,Denied,Void'
            ];
        }
    }

    function messages()
    {
        return [
            'asset_approval_id.required' => 'The asset approval is required',
            'asset_approval_id.exists' => 'The asset approval does not exist',
            'asset_approval_id.array' => 'The asset approval must be an array',
            'action.required' => 'The action is required',
            'action.string' => 'The action must be a string',
            'action.in' => 'Invalid Selection',
        ];
    }
}
