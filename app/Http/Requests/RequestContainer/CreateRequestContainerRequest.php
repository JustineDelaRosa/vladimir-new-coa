<?php

namespace App\Http\Requests\RequestContainer;

use App\Models\Location;
use App\Models\SubUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRequestContainerRequest extends FormRequest
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
            'type_of_request_id' => [
                'required',
                Rule::exists('type_of_requests', 'id')
            ],
//            'department_id.company.company_id' => [
//                'required',
//                Rule::exists('companies', 'id')
//            ],
            'company_id' => ['required', Rule::exists('companies', 'id')],
            'department_id' => ['required', Rule::exists('departments', 'id')],
            'attachment_type' => 'required|in:Budgeted,Unbudgeted',
            'subunit_id' => ['required', Rule::exists('sub_units', 'id'),
                //check if the subunit already has approvers assigned
                function ($attribute, $value, $fail) {
                    $subunit = SubUnit::find($value);
                    if ($subunit->departmentUnitApprovers->isEmpty()) {
                        $fail('No approvers assigned to the selected subunit.');
                    }
                    //check if this is the sub unit of the selected department
                    if ($subunit->department_id != request()->department_id) {
                        $fail('Subunit does not match department.');
                    }
                },
            ],
            'location_id' => ['required', Rule::exists('locations', 'id'),
                    //check if the location and department combination exists
                    function ($attribute, $value, $fail) {
                        $location = Location::find($value);
                        $departments = $location->departments->pluck('id')->toArray();
                        if (!in_array(request()->department_id, $departments)) {
                            $fail('Invalid combination of location and department.');
                        }

                    },
                ],
            'account_title_id' => ['required', Rule::exists('account_titles', 'id')],
            'accountability' => 'required|in:Personal Issued,Common',
            'accountable' => ['required_if:accountability,Personal Issued',
                function ($attribute, $value, $fail) {
                    $accountable = request()->input('accountable');
                    //if the accountability is not Personal Issued, nullify the accountable field and return
                    if (request()->accountability != 'Personal Issued') {
                        request()->merge(['accountable' => null]);
                        return;
                    }

//                    // Get full ID number if it exists or fail validation
//                    if (!empty($accountable['general_info']['full_id_number'])) {
//                        $full_id_number = trim($accountable['general_info']['full_id_number']);
//                        request()->merge(['accountable' => $full_id_number]);
//                    } else {
//                        $fail('The accountable person is required.');
//                        return;
//                    }
//
//                    // Validate full ID number
//                    if ($value->isEmpty()) {
//                        $fail('The accountable person cannot be empty.');
//                    }
                },
            ],
            'asset_description' => 'required',
            'asset_specification' => 'nullable',
            'cellphone_number' => 'nullable|numeric',
            'brand' => 'nullable',
            'quantity' => 'required|numeric|min:1',
        ];
    }

    function messages()
    {
        return [
            'type_of_request_id.required' => 'The type of request is required.',
            'type_of_request_id.exists' => 'The type of request is invalid.',
            'department_id.required' => 'The department is required.',
            'department_id.exists' => 'The department is invalid.',
            'attachment_type.required' => 'The attachment type is required.',
            'attachment_type.in' => 'The attachment type is invalid.',
            'subunit_id.required' => 'The subunit is required.',
            'subunit_id.exists' => 'The subunit is invalid.',
            'location_id.required' => 'The location is required.',
            'location_id.exists' => 'The location is invalid.',
            'account_title_id.required' => 'The account title is required.',
            'account_title_id.exists' => 'The account title is invalid.',
            'accountability.required' => 'The accountability is required.',
//            'department.company.company_id.required' => 'The company is required.',
//            'department.company.company_id.exists' => 'The company is invalid.',
            'accountability.in' => 'The accountability is invalid.',
            'accountable.required_if' => 'The accountable is required.',
//            'accountable.exists' => 'The accountable is invalid.',
            'asset_description.required' => 'The asset description is required.',
            'asset_specification.required' => 'The asset specification is required.',
            'cellphone_number.numeric' => 'The cellphone number must be a number.',
            'brand.required' => 'The brand is required.',
            'quantity.required' => 'The quantity is required.',
            'quantity.numeric' => 'The quantity must be a number.',
            'quantity.min' => 'The quantity must be at least 1.',
            /*'userRequest.*.letter_of_request.file' => 'The letter of request must be a file.',
            'userRequest.*.letter_of_request.mimes' => 'The letter of request must be a file of type: pdf, doc, docx, xls, xlsx.',
            'userRequest.*.letter_of_request.max' => 'The letter of request may not be greater than 10000 kilobytes.',
            'userRequest.*.quotation.file' => 'The quotation must be a file.',
            'userRequest.*.quotation.mimes' => 'The quotation must be a file of type: pdf, doc, docx, xls, xlsx.',
            'userRequest.*.quotation.max' => 'The quotation may not be greater than 10000 kilobytes.',
            'userRequest.*.specification_form.file' => 'The specification form must be a file.',
            'userRequest.*.specification_form.mimes' => 'The specification form must be a file of type: pdf, doc, docx, xls, xlsx.',
            'userRequest.*.specification_form.max' => 'The specification form may not be greater than 10000 kilobytes.',
            'userRequest.*.tool_of_trade.file' => 'The tool of trade must be a file.',
            'userRequest.*.tool_of_trade.mimes' => 'The tool of trade must be a file of type: pdf, doc, docx, xls, xlsx.',
            'userRequest.*.tool_of_trade.max' => 'The tool of trade may not be greater than 10000 kilobytes.',
            'userRequest.*.other_attachments.file' => 'The other attachments must be a file.',
            'userRequest.*.other_attachments.mimes' => 'The other attachments must be a file of type: pdf, doc, docx, xls, xlsx.',
            'userRequest.*.other_attachments.max' => 'The other attachments may not be greater than 10000 kilobytes.',*/
        ];
    }
}