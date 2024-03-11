<?php

namespace App\Http\Requests\SubUnit;

use App\Models\SubUnit;
use Illuminate\Foundation\Http\FormRequest;

class CreateSubUnitRequest extends FormRequest
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
                'department_id' => 'required|exists:departments,id',
                'subunit_name' => ['bail', 'required', 'string', 'max:255', 'unique:sub_units,sub_unit_name',
                    function ($attribute, $value, $fail) {
                        if ($this->department_id) {
                            $subUnit = SubUnit::withTrashed()->where('department_id', $this->department_id)->where('sub_unit_name', $value)->exists();
                            if ($subUnit) {
                                $fail('Sub Unit already exists');
                            }
                        }
                    }
                ],
            ];
        }

        if ($this->isMethod("PATCH")) {
            return [
                'status' => 'required|boolean',
            ];
        }
    }

    function message()
    {
        return [
            'department_id.required' => 'Department is required',
            'department_id.exists' => 'Department does not exists',
            'sub_unit_name.unique' => 'Sub Unit Name already exists',
            'sub_unit_name.required' => 'Sub Unit Name is required',
            'sub_unit_name.string' => 'Invalid Sub Unit Name',
            'sub_unit_name.max' => 'Invalid Sub Unit Character Length',
            'status.required' => 'Status is required',
            'status.boolean' => 'Invalid Status',
        ];
    }
}
