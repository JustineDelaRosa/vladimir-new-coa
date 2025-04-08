<?php

namespace App\Http\Requests\AccountingEntries;

use App\Models\Department;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Foundation\Http\FormRequest;

class CreateAccountingEntriesRequest extends FormRequest
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
//            'initial_debit_id' => 'required|exists:account_titles,id',
//            'initial_credit_id' => 'required|exists:account_titles,id',
            'depreciation_debit_id' => 'required|exists:account_titles,id',
//            'depreciation_credit_id' => 'required|exists:account_titles,id',
            'company_id' => 'nullable|exists:companies,id',
            'business_unit_id' => ['nullable', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['nullable', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['nullable', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['nullable', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, true)],
            'location_id' => ['nullable', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
        ];
    }

    public function messages()
    {
        return [
            'initial_debit_id.required' => 'The initial debit field is required.',
            'initial_debit_id.exists' => 'The selected initial debit is invalid.',
            'initial_credit_id.required' => 'The initial credit field is required.',
            'initial_credit_id.exists' => 'The selected initial credit is invalid.',
            'depreciation_debit_id.required' => 'The depreciation debit field is required.',
            'depreciation_debit_id.exists' => 'The selected depreciation debit is invalid.',
            'depreciation_credit_id.required' => 'The depreciation credit field is required.',
            'depreciation_credit_id.exists' => 'The selected depreciation credit is invalid.',
            'company_id.exists' => 'The selected company is invalid.',
            'business_unit_id.exists' => 'The selected business unit is invalid.',
            'department_id.exists' => 'The selected department is invalid.',
            'unit_id.exists' => 'The selected unit is invalid.',
            'subunit_id.exists' => 'The selected subunit is invalid.',
            'location_id.exists' => 'The selected location is invalid.',
            'business_unit_id.required' => 'The business unit field is required.',
            'department_id.required' => 'The department field is required.',
            'unit_id.required' => 'The unit field is required.',
            'subunit_id.required' => 'The subunit field is required.',
            'location_id.required' => 'The location field is required.',

        ];
    }
}
