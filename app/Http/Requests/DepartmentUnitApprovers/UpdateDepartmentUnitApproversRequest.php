<?php

namespace App\Http\Requests\DepartmentUnitApprovers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentUnitApproversRequest extends FormRequest
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
                'unique_in_array'
            ],
        ];
    }
}
