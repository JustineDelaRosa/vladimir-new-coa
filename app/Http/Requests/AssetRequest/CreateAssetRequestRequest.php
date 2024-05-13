<?php

namespace App\Http\Requests\AssetRequest;

use App\Models\ApproverLayer;
use App\Models\Capex;
use App\Models\TypeOfRequest;
use App\Repositories\ApprovedRequestRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAssetRequestRequest extends FormRequest
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
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            //            $typeOfRequestIdForCapex = TypeOfRequest::where('type_of_request_name', 'Capex')->first()->id;
            //            return [
            //                'requester_id' => [request()->merge(['requester_id' => $requesterId])],
            //                'type_of_request_id' => [
            //                    'required',
            //                    Rule::exists('type_of_requests', 'id')
            //                ],
            //                'sub_capex_id' => ['required_if:type_of_request_id,' . $typeOfRequestIdForCapex, Rule::exists('sub_capexes', 'id')],
            //                'asset_description' => 'required',
            //                'asset_specification' => 'nullable',
            //                'accountability' => 'required|in:Personal Issued,Common',
            //                'accountable' => ['required_if:accountability,Personal Issued', 'exists:users,id',
            //                    function ($attribute, $value, $fail) {
            //                        $accountable = request()->input('accountable');
            //                        //if the accountability is not Personal Issued, nullify the accountable field and return
            //                        if (request()->accountability != 'Personal Issued') {
            //                            request()->merge(['accountable' => null]);
            //                            return;
            //                        }
            //
            //                        // Get full ID number if it exists or fail validation
            //                        if (!empty($accountable['general_info']['full_id_number'])) {
            //                            $full_id_number = trim($accountable['general_info']['full_id_number']);
            //                            request()->merge(['accountable' => $full_id_number]);
            //                        } else {
            //                            $fail('The accountable person is required.');
            //                            return;
            //                        }
            //
            //                        // Validate full ID number
            //                        if (empty($full_id_number)) {
            //                            $fail('The accountable person cannot be empty.');
            //                        }
            //                    },
            //                ],
            //                'cellphone_number' => 'nullable|numeric',
            //                'brand' => 'nullable',
            //                'quantity' => 'required|numeric|min:1',
            //            ];
            return [
                //                'userRequest' => ['required','array'],


                'userRequest.*.type_of_request_id.id' => [
                    'required',
                    Rule::exists('type_of_requests', 'id')
                ],
                'userRequest.*.department_id.company.company_id' => [
                    'required',
                    Rule::exists('companies', 'id')
                ],
                'userRequest.*.department_id.id' => ['required', Rule::exists('departments', 'id')],
                'userRequest.*.attachment_type' => 'required|in:Budgeted,Unbudgeted',
                'userRequest.*.subunit_id.id' => ['required', Rule::exists('sub_units', 'id')],
                'userRequest.*.location_id.id' => ['required', Rule::exists('locations', 'id')],
                'userRequest.*.account_title_id.id' => ['required', Rule::exists('account_titles', 'id')],
                'userRequest.*.accountability' => 'required|in:Personal Issued,Common',
                'userRequest.*.accountable' => [
                    'required_if:accountability,Personal Issued',
                    function ($attribute, $value, $fail) {
                        $accountable = request()->input('accountable');
                        //if the accountability is not Personal Issued, nullify the accountable field and return
                        if (request()->accountability != 'Personal Issued') {
                            request()->merge(['accountable' => null]);
                            return;
                        }

                        // Get full ID number if it exists or fail validation
                        if (!empty($accountable['general_info']['full_id_number'])) {
                            $full_id_number = trim($accountable['general_info']['full_id_number']);
                            request()->merge(['accountable' => $full_id_number]);
                        } else {
                            $fail('The accountable person is required.');
                            return;
                        }

                        // Validate full ID number
                        if (empty($full_id_number)) {
                            $fail('The accountable person cannot be empty.');
                        }
                    },
                ],
                'userRequest.*.asset_description' => 'required',
                'userRequest.*.asset_specification' => 'nullable',
                'userRequest.*.cellphone_number' => 'nullable',
                'userRequest.*.brand' => 'nullable',
                'userRequest.*.quantity' => 'required|numeric|min:1',
                'userRequest.*.letter_of_request' => 'nullable|file|mimes:pdf,doc,docx|max:10000',
                'userRequest.*.quotation' => 'nullable|file|mimes:pdf,doc,docx|max:10000',
                'userRequest.*.specification_form' => 'nullable|file|mimes:pdf,doc,docx|max:10000',
                'userRequest.*.tool_of_trade' => 'nullable|file|mimes:pdf,doc,docx|max:10000',
                'userRequest.*.other_attachments' => 'nullable|file|mimes:pdf,doc,docx|max:10000',
            ];
        }

        if ($this->isMethod('PATCH')) {
            return [
                'transaction_number' => ['required', 'exists:asset_requests,transaction_number'],
            ];
        }
        return [];
    }

    function messages(): array
    {
        return [
            'userRequest.*.type_of_request_id.id.required' => 'The type of request is required.',
            'userRequest.*.type_of_request_id.id.exists' => 'The type of request is invalid.',
            'userRequest.*.department_id.id.required' => 'The department is required.',
            'userRequest.*.department_id.id.exists' => 'The department is invalid.',
            'userRequest.*.attachment_type.required' => 'The attachment type is required.',
            'userRequest.*.attachment_type.in' => 'The attachment type is invalid.',
            'userRequest.*.subunit_id.id.required' => 'The subunit is required.',
            'userRequest.*.subunit_id.id.exists' => 'The subunit is invalid.',
            'userRequest.*.location_id.id.required' => 'The location is required.',
            'userRequest.*.location_id.exists' => 'The location is invalid.',
            'userRequest.*.account_title_id.id.required' => 'The account title is required.',
            'userRequest.*.account_title_id.id.exists' => 'The account title is invalid.',
            'userRequest.*.accountability.required' => 'The accountability is required.',
            'userRequest.*.department.company.company_id.required' => 'The company is required.',
            'userRequest.*.department.company.company_id.exists' => 'The company is invalid.',
            'userRequest.*.accountability.in' => 'The accountability is invalid.',
            'userRequest.*.accountable.required_if' => 'The accountable is required.',
            //            'userRequest.*.accountable.exists' => 'The accountable is invalid.',
            'userRequest.*.asset_description.required' => 'The asset description is required.',
            'userRequest.*.asset_specification.required' => 'The asset specification is required.',
            'userRequest.*.cellphone_number.numeric' => 'The cellphone number must be a number.',
            'userRequest.*.brand.required' => 'The brand is required.',
            'userRequest.*.quantity.required' => 'The quantity is required.',
            'userRequest.*.quantity.numeric' => 'The quantity must be a number.',
            'userRequest.*.quantity.min' => 'The quantity must be at least 1.',
            'userRequest.*.letter_of_request.file' => 'The letter of request must be a file.',
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
            'userRequest.*.other_attachments.max' => 'The other attachments may not be greater than 10000 kilobytes.',
        ];
    }
}
