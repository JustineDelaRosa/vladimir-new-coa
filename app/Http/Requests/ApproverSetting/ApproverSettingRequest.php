<?php

namespace App\Http\Requests\ApproverSetting;

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
        if($this->isMethod("POST")){
            return [
                'requester_id' => [
                    'required',
                    'exists:users,id',
                    Rule::notIn($this->approver_id),
                ],
                'approver_id' =>[
                    'required',
                    'exists:users,id',
                    'array',
                    'unique_in_array'
                ],
            ];
        }
        if($this->isMethod("PUT") && ($this->route()->parameter('approver_setting'))){
            $id = $this->route()->parameter('approver_setting');
            return [
                'approver_id' =>[
                    'required',
                    'exists:users,id',
                    function ($attribute, $value, $fail) use ($id) {
                        $userApprover = UserApprover::where('approver_id', $value)->where('id', '!=', $id)->first();
                        if ($userApprover) {
                            $fail('Approver already exists.');
                        }
                        //cannot be his own approver
                        $approver = UserApprover::where('id', $id)->first();
                        if ($approver->requester_id == $value) {
                            $fail('Requester cannot be his own approver.');
                        }
                    },
                ],
            ];
        }

        if($this->isMethod("PATCH") && ($this->route()->parameter('id'))){
            return [
                'status' => 'required|boolean'
            ];
        }
    }

    function messages()
    {
        return [
            'requester_id.required' => 'Requester is required',
            'requester_id.exists' => 'Requester does not exist',
            'requester_id.not_in' => 'Requester cannot add himself as approver',
            'approver_id.required' => 'Approver is required',
            'approver_id.exists' => 'Approver does not exist',
            'approver_id.array' => 'Approver must be an array',
            'approver_id.unique_in_array' => 'Approvers must be unique',
        ];
    }
}
