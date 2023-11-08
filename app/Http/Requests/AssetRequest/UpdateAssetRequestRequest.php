<?php

namespace App\Http\Requests\AssetRequest;

use App\Models\TypeOfRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequestRequest extends FormRequest
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
        $typeOfRequestIdForCapex = TypeOfRequest::where('type_of_request_name', 'Capex')->first()->id;
        return [
            'type_of_request_id' => [
                'required',
                Rule::exists('type_of_requests', 'id')
            ],
//            'charged_department_id' => ['required', Rule::exists('departments', 'id')],
            'attachment_type' => 'required|in:Budgeted,Unbudgeted',
//            'subunit_id' =>['required', Rule::exists('sub_units', 'id')],

            'accountability' => 'required|in:Personal Issued,Common',
            'accountable' => ['required_if:accountability,Personal Issued', 'exists:users,id',
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
            'asset_description' => 'required',
            'asset_specification' => 'nullable',
            'cellphone_number' => 'nullable|numeric',
            'brand' => 'nullable',
            'quantity' => 'required|numeric',
            'letter_of_request' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
            'quotation' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
            'specification_form' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
            'tool_of_trade' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
            'other_attachments' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
        ];
    }

    function messages(): array
    {
        return [
            'type_of_request_id.required' => 'The type of request field is required',
            'type_of_request_id.exists' => 'The selected type of request is invalid',
            'sub_capex_id.required_if' => 'The sub capex field is required when type of request is capex',
            'sub_capex_id.exists' => 'The selected sub capex is invalid',
            'asset_description.required' => 'The asset description field is required',
            'accountability.required' => 'The accountability field is required',
            'accountability.in' => 'The selected accountability is invalid',
            'accountable.required_if' => 'The accountable field is required when accountability is personal issued',
            'accountable.exists' => 'The selected accountable is invalid',
//            'accountable.validateAccountable' => 'The selected accountable is invalids',
            'cellphone_number.numeric' => 'The cellphone number must be a number',
            'brand.required' => 'The brand field is required',
            'quantity.required' => 'The quantity field is required',
            'quantity.numeric' => 'The quantity must be a number',

        ];
    }
}
