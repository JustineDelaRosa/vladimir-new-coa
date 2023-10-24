<?php

namespace App\Http\Requests\SubUnit;

use App\Models\SubUnit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubUnitRequest extends FormRequest
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
        $id = $this->route()->parameter('sub_unit');
        return [
            'department_id' => ['bail', 'required', 'exists:departments,id'],
            'subunit_name' => ['bail', 'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use($id) {
                    if ($this->department_id) {
                        $subUnit = SubUnit::withTrashed()->where('department_id', $this->department_id)
                            ->where('sub_unit_name', $value)
                            ->where('id', '!=', $id)
                            ->exists();
                        if ($subUnit) {
                            $fail('Sub Unit already exists');
                        }
                    }
                }
            ],
        ];
    }
}
