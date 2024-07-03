<?php

namespace App\Http\Requests\AssetRelease;

use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Foundation\Http\FormRequest;

class MultipleReleaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'warehouse_number_id' => ['bail','required', 'exists:warehouse_numbers,id', 'distinct', 'array'],
            'accountability' => ['required', 'string', 'in:Common,Personal Issued'],
            'accountable' => ['required_if:accountability,Personal Issued', 'string'],
            'received_by' => ['required', 'string'],
//            'signature' => ['required'],
            'receiver_img' => ['required'],
            'assignment_memo_img' => ['required'],
            'authorization_memo_img' => ['required'],
            'company_id' => 'nullable|exists:companies,id',
            'business_unit_id' => ['nullable', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['nullable', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['nullable', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['nullable', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, false)],
            'location_id' => ['nullable', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
            'account_title_id' => 'nullable|exists:account_titles,id',
        ];
    }

    function messages(): array
    {
        return [
            'warehouse_number_id.required' => 'Warehouse Number is required',
            'warehouse_number_id.exists' => 'Warehouse Number does not exist',
            'warehouse_number_id.distinct' => 'Warehouse Number must be unique',
            'accountability.required' => 'Accountability is required',
            'accountability.string' => 'Accountability must be a string',
            'accountability.in' => 'Accountability must be either Common or Personal Issued',
            'accountable.required_if' => 'Accountable is required',
            'accountable.string' => 'Accountable must be a string',
            'received_by.required' => 'Received By is required',
            'received_by.string' => 'Received By must be a string',
//            'signature.required' => 'Signature is required',
            'receiver_img.required' => 'Receiver Image is required',
            'assignment_memo_img.required' => 'Assignment Memo Image is required',
            'authorization_memo_img.required' => 'Authorization Memo Image is required',
            'company_id.exists' => 'Company does not exist',
            'business_unit_id.exists' => 'Business Unit does not exist',
            'department_id.exists' => 'Department does not exist',
            'unit_id.exists' => 'Unit does not exist',
            'subunit_id.exists' => 'Sub Unit does not exist',
            'location_id.exists' => 'Location does not exist',
            'account_title_id.exists' => 'Account Title does not exist',
        ];
    }
}
