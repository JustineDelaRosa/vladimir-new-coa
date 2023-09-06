<?php

namespace App\Http\Requests\ApproverSetting;

use App\Models\Approvers;
use App\Models\UserApprover;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproverSettingRequest extends FormRequest
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
        if ($this->isMethod("POST")) {
            return [
                'approver_id' => [
                    'required',
                    'exists:users,id,deleted_at,NULL',
                    function ($attribute, $value, $fail) {
                        //check if this approver is already axist in the approvers table
                        $approver = Approvers::withTrashed()->where('approver_id', $value)->first();
                        if ($approver) {
                            $fail('Approver already exists.');
                        }
                    },
                ],
            ];
        }
        if ($this->isMethod("PUT") && ($this->route()->parameter('approver_setting'))) {
            $id = $this->route()->parameter('approver_setting');
            return [
                'approver_id' => [
                    'required',
                    'exists:users,id,deleted_at,NULL',
                    function ($attribute, $value, $fail) use ($id) {
                        //check if this approver is already axist in the approvers table except the current id
                        $approver = Approvers::withTrashed()->where('approver_id', $value)->where('id', '!=', $id)->first();
                        if ($approver) {
                            $fail('Approver already exists.');
                        }
                    },
                ],
            ];
        }

        if ($this->isMethod("PATCH") && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean'
            ];
        }
    }

    function messages()
    {
        return [
            'approver_id.required' => 'Approver is required.',
            'approver_id.exists' => 'User does not exist.',
            'status.required' => 'Status is required.',
            'status.boolean' => 'Status must be a boolean.',
        ];
    }
}
