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
            'type_of_request_id.id' => [
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
            'letter_of_request' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
            'quotation' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
            'specification_form' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
            'tool_of_trade' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
            'other_attachments' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10000',
        ];
    }
}
