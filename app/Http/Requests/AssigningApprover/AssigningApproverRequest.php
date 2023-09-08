<?php

namespace App\Http\Requests\AssigningApprover;

use App\Models\Approvers;
use App\Models\ApproverLayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssigningApproverRequest extends FormRequest
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
                'requester_id' => [
                    'required',
                    'exists:users,id,deleted_at,NULL',
                    function ($attribute, $value, $fail) {
                        //check if this requester is already axist in the approvers table
                        $approver = Approvers::whereIn('id', $this->approver_id)->get();
                        foreach ($approver as $item) {
                            if ($item->approver_id == $value) {
                                $fail('Requester cannot be his own approver.');
                            }
                        }
                    },
                ],
                'approver_id' => [
                    'required',
                    'exists:approvers,id,deleted_at,NULL',
                    'array',
                    'unique_in_array',
                    function ($attribute, $value, $fail) {
                        //check if this approver already exists in the approver table of the requester
                        $approver = ApproverLayer::where('requester_id', $this->requester_id)->whereIn('approver_id', $value)->first();
                        if ($approver) {
                            $fail('Approver already exists.');
                        }
                    },
                ],
            ];
        }

        if ($this->isMethod("PUT") && ($this->route()->parameter('assigning_approver'))) {
            $id = $this->route()->parameter('assigning_approver');
            return [
                'approver_id' => [
                    'required',
                    'exists:users,id,deleted_at,NULL',
                    'array',
                    'unique_in_array',
                    function ($attribute, $value, $fail) use ($id) {
                        //check if this approver already exists in the approver table of the requester except the current id
                        $approver = ApproverLayer::where('requester_id', $this->requester_id)->whereIn('approver_id', $value)->where('id', '!=', $id)->first();
                        if ($approver) {
                            $fail('Approver already exists.');
                        }

                        //check if this approver already exists in the approver table of the requester
                        $approver = ApproverLayer::where('requester_id', $this->requester_id)->whereIn('approver_id', $value)->first();
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
            'approver_id.required' => 'The approver field is required.',
            'approver_id.exists' => 'The selected approver does not exist.',
            'approver_id.unique_in_array' => 'Duplicate approver is not allowed.',
            'requester_id.required' => 'The requester field is required.',
            'requester_id.exists' => 'The selected requester is invalid.',
            'requester_id.not_in' => 'Requester cannot be his own approver.',
        ];
    }
}
