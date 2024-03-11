<?php

namespace App\Http\Requests\RequestContainer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestContainerRequest extends FormRequest
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
            'attachment_type' => 'required|in:Budgeted,Unbudgeted',
            'accountability' => 'required|in:Personal Issued,Common',
            'accountable' => ['required_if:accountability,Personal Issued',
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
//            'letter_of_request' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
//            'quotation' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
//            'specification_form' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
//            'tool_of_trade' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
//            'other_attachments' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
        ];
    }

    function message(){
        return[
            'type_of_request_id.required' => 'The type of request field is required.',
            'type_of_request_id.exists' => 'The selected type of request is invalid.',
            'attachment_type.required' => 'The attachment type field is required.',
            'attachment_type.in' => 'The selected attachment type is invalid.',
            'accountability.required' => 'The accountability field is required.',
            'accountability.in' => 'The selected accountability is invalid.',
            'accountable.required_if' => 'The accountable field is required.',
            'asset_description.required' => 'The asset description field is required.',
            'quantity.required' => 'The quantity field is required.',
            'quantity.numeric' => 'The quantity must be a number.',
            'letter_of_request.file' => 'The letter of request must be a file.',
            'letter_of_request.mimes' => 'The letter of request must be a file of type: pdf, doc, docx, xls, xlsx.',
            'letter_of_request.max' => 'The letter of request may not be greater than 10000 kilobytes.',
            'quotation.file' => 'The quotation must be a file.',
            'quotation.mimes' => 'The quotation must be a file of type: pdf, doc, docx, xls, xlsx.',
            'quotation.max' => 'The quotation may not be greater than 10000 kilobytes.',
            'specification_form.file' => 'The specification form must be a file.',
            'specification_form.mimes' => 'The specification form must be a file of type: pdf, doc, docx, xls, xlsx.',
            'specification_form.max' => 'The specification form may not be greater than 10000 kilobytes.',
            'tool_of_trade.file' => 'The tool of trade must be a file.',
            'tool_of_trade.mimes' => 'The tool of trade must be a file of type: pdf, doc, docx, xls, xlsx.',
            'tool_of_trade.max' => 'The tool of trade may not be greater than 10000 kilobytes.',
            'other_attachments.file' => 'The other attachments must be a file.',
            'other_attachments.mimes' => 'The other attachments must be a file of type: pdf, doc, docx, xls, xlsx.',
            'other_attachments.max' => 'The other attachments may not be greater than 10000 kilobytes.',

        ];
    }
}
