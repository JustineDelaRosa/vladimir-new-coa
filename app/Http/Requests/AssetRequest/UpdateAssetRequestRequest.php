<?php

namespace App\Http\Requests\AssetRequest;

use App\Rules\FileOrX;
use App\Models\TypeOfRequest;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

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
            'accountable' => [
                'required_if:accountability,Personal Issued',
                function ($attribute, $value, $fail) {
                    $accountable = request()->input('accountable');
                    //if the accountability is not Personal Issued, nullify the accountable field and return
                    if (request()->accountability != 'Personal Issued') {
                        request()->merge(['accountable' => null]);
                        return;
                    }
                }
            ],
            'asset_description' => 'required',
            'asset_specification' => 'nullable',
            'cellphone_number' => 'nullable|numeric',
            'brand' => 'nullable',
            'quantity' => 'required|numeric',
            'letter_of_request' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'quotation' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'specification_form' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'tool_of_trade' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'other_attachments' => ['bail', 'nullable', 'max:10000', new FileOrX],
            //
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
            'letter_of_request.file' => 'The letter of request must be a file',
            'letter_of_request.mimes' => 'The letter of request must be a file of type: pdf, doc, docx',
            'letter_of_request.max' => 'The letter of request may not be greater than 10 megabytes',
            'quotation.file' => 'The quotation must be a file',
            'quotation.mimes' => 'The quotation must be a file of type: pdf, doc, docx',
            'quotation.max' => 'The quotation may not be greater than 10 megabytes',
            'specification_form.file' => 'The specification form must be a file',
            'specification_form.mimes' => 'The specification form must be a file of type: pdf, doc, docx',
            'specification_form.max' => 'The specification form may not be greater than 10 megabytes',
            'tool_of_trade.file' => 'The tool of trade must be a file',
            'tool_of_trade.mimes' => 'The tool of trade must be a file of type: pdf, doc, docx',
            'tool_of_trade.max' => 'The tool of trade may not be greater than 10 megabytes',
            'other_attachments.file' => 'The other attachments must be a file',
            'other_attachments.mimes' => 'The other attachments must be a file of type: pdf, doc, docx',
            'other_attachments.max' => 'The other attachments may not be greater than 10 megabytes',


        ];
    }
}
