<?php

namespace App\Http\Requests\CoordinatorHandleRequest;

use App\Models\User;
use App\Rules\DuplicateHandle;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCoordinatorHandleRequest extends FormRequest
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
            'user_id' => ['required', 'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if (!$user->is_coordinator) {
                        $fail('User is not a coordinator');
                    }
                },
            ],
            'handles' => ['required', new DuplicateHandle($this->input('handles', []))],
            'handles.*.company_id' => ['required', 'exists:companies,id'],
            'handles.*.business_unit_id' => ['required', 'exists:business_units,id'],
            'handles.*.department_id' => ['required', 'exists:departments,id'],
            'handles.*.unit_id' => ['required', 'exists:units,id'],
            'handles.*.subunit_id' => ['required', 'exists:sub_units,id'],
            'handles.*.location_id' => ['required', 'exists:locations,id'],
        ];
    }

    function messages()
    {
        return [
            'user_id.required' => 'User is required',
            'user_id.exists' => 'User does not exist',
            'user_id.unique' => 'User already has a handle',
            'handles.*.company_id.required' => 'Company is required',
            'handles.*.company_id.exists' => 'Company does not exist',
            'handles.*.business_unit_id.required' => 'Business unit is required',
            'handles.*.business_unit_id.exists' => 'Business unit does not exist',
            'handles.*.department_id.required' => 'Department is required',
            'handles.*.department_id.exists' => 'Department does not exist',
            'handles.*.unit_id.required' => 'Unit is required',
            'handles.*.unit_id.exists' => 'Unit does not exist',
            'handles.*.subunit_id.required' => 'Subunit is required',
            'handles.*.subunit_id.exists' => 'Subunit does not exist',
            'handles.*.location_id.required' => 'Location is required',
            'handles.*.location_id.exists' => 'Location does not exist',
        ];
    }
}
