<?php

namespace App\Http\Requests\FormSettingBase;

use Illuminate\Foundation\Http\FormRequest;

class UnitApproverUpdateBaseRequest extends FormRequest
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

    public function rules()
    {
        return [
//            'department_id' =>[
//                'required',
//                'integer',
//                'exists:departments,id'
//            ],
//            'subunit_id' =>[
//                'required',
//                'integer',
//                'exists:sub_units,id'
//            ],
            'approver_id' =>[
                'required',
                'exists:approvers,id',
                'array',
                'unique_in_array',
                'min:2'
            ],
        ];
    }

    function messages()
    {
        return [
            'department_id.required' => 'Department is required.',
            'department_id.integer' => 'Department must be an integer.',
            'department_id.exists' => 'Department does not exist.',
            'subunit_id.required' => 'Subunit is required.',
            'subunit_id.integer' => 'Subunit must be an integer.',
            'subunit_id.exists' => 'Subunit does not exist.',
            'approver_id.required' => 'Approver is required.',
            'approver_id.integer' => 'Approver must be an integer.',
            'approver_id.exists' => 'Approver does not exist.',
            'approver_id.array' => 'Invalid approver.',
            'approver_id.unique_in_array' => 'Approver must be unique.',
            'approver_id.min' => 'At least 2 approvers are required.',
        ];
    }
}
