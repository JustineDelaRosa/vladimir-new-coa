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
                'transaction_number' => 'required|exists:asset_requests,transaction_number',
                //'asset_request_id' => 'one_array_present:asset_approval_id|exists:asset_requests,id|array',
                'action' => 'required|string|in:Approve,Return',
                'remarks' => ['required_if:action,Return', 'string'],
            ];
        }
    }

    function messages(): array
    {
        return [
            'asset_approval_id.one_array_present' => 'either asset approval or asset request is required',
            'asset_approval_id.exists' => 'The asset approval does not exist',
            'asset_approval_id.array' => 'The asset approval must be an array',
            'asset_request_id.one_array_present' => 'either asset approval or asset request is required',
            'asset_request_id.exists' => 'The asset request does not exist',
            'asset_request_id.array' => 'The asset request must be an array',
            'action.required' => 'The action is required',
            'action.string' => 'The action must be a string',
            'action.in' => 'The selected action is invalid',
            'remarks.required_if' => 'The remarks is required',
            'remarks.string' => 'The remarks must be a string',
        ];
    }
}
