<?php

namespace App\Http\Requests\AdditionalCost;

use Illuminate\Foundation\Http\FormRequest;

class TaggingOfAddCostRequest extends FormRequest
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
            'added_useful_life' => 'required|integer',
//            'company_id' => 'required|exists:companies,id',
//            'business_unit_id' => 'required|exists:business_units,id',
//            'department_id' => 'required|exists:departments,id',
//            'unit_id' => 'required|exists:units,id',
//            'subunit_id' => 'required|exists:sub_units,id',
//            'location_id' => 'required|exists:locations,id',
        ];
    }

    public function messages()
    {
        return [
            'est_useful_life.required' => 'Estimated useful life is required',
            'est_useful_life.integer' => 'Invalid estimated useful life',
            'company_id.required' => 'Company is required',
            'company_id.exists' => 'Company does not exist',
            'business_unit_id.required' => 'Business unit is required',
            'business_unit_id.exists' => 'Business unit does not exist',
            'department_id.required' => 'Department is required',
            'department_id.exists' => 'Department does not exist',
            'unit_id.required' => 'Unit is required',
            'unit_id.exists' => 'Unit does not exist',
            'subunit_id.required' => 'Subunit is required',
            'subunit_id.exists' => 'Subunit does not exist',
            'location_id.required' => 'Location is required',
            'location_id.exists' => 'Location does not exist',
        ];
    }
}
