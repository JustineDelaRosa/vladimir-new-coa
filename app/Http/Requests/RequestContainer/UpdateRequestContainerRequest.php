<?php

namespace App\Http\Requests\RequestContainer;

use App\Models\TypeOfRequest;
use App\Rules\FileOrX;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
            'capex_number' => 'nullable',
            'attachment_type' => 'required|in:Budgeted,Unbudgeted',
            'is_addcost' => 'nullable|boolean',
            'fixed_asset_id' => [
                'required-if:is_addcost,true',
                Rule::exists('fixed_assets', 'id'),
                //check if this has different fixed asset id from other request container
                function ($attribute, $value, $fail) {
//                    $userId = auth()->user()->id;
//                    $requestContainerFA = RequestContainer::where('requester_id', $userId)->first()->fixed_asset->id ?? null;
//                    if(!$requestContainerFA){
//                        return;
//                    }
//                    if($requestContainerFA !== $value){
//                        $fail('The selected fixed asset is different from the other item.');
//                    }
                },
            ],

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
                },
            ],
            'small_tool_id' => [
                function ($attribute, $value, $fail) {
                    $typeOfRequestId = request()->input('type_of_request_id');
                    $smallToolsId = TypeOfRequest::where('type_of_request_name', 'Small Tools')->first()->id;

                    if ($typeOfRequestId == $smallToolsId) {
                        if (empty($value)) {
                            $fail('The small tool is required.');
                        } elseif (!DB::table('small_tools')->where('id', $value)->exists()) {
                            $fail('The small tool is invalid.');
                        }
                    } else {
                        request()->merge(['small_tool_id' => null]);
                    }
                },
            ],
            'additional_info' => 'nullable',
            'acquisition_details' => 'required|string',
            'asset_description' => 'required',
            'asset_specification' => 'nullable',
            'cellphone_number' => 'nullable|digits_between:11,12',
            'brand' => 'nullable',
            'quantity' => 'required|numeric|min:1',
            'date_needed' => 'required|date|after_or_equal:today',
            'letter_of_request' => ['bail', 'nullable', 'required-if:attachment_type,Unbudgeted', 'max:10000', new FileOrX],
            'quotation' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'specification_form' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'tool_of_trade' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'other_attachments' => ['nullable', 'required-if:type_of_request_id,2', 'max:10000', new FileOrX],
            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, true)],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
//            'account_title_id' => 'required|exists:account_titles,id',
            'uom_id' => 'required|exists:unit_of_measures,id',
        ];
    }

    function messages(): array
    {
        return [
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
            'cellphone_number.digits_between' => 'Invalid cellphone number.',
            'type_of_request_id.required-if' => 'The type of request field is required.',
            'letter_of_request.required_if' => 'The letter of request is required.',
            'other_attachments.required_if' => 'The other attachments is required.',
        ];
    }
}
