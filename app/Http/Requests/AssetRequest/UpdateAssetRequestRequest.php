<?php

namespace App\Http\Requests\AssetRequest;

use App\Models\SubUnit;
use App\Rules\FileOrX;
use App\Models\TypeOfRequest;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Support\Facades\DB;
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
            'capex_number' => 'nullable',
            'date_needed' => 'required|date',
            'is_addcost' => 'nullable|in:0,1',
            'fixed_asset_id' => ['required-if:is_addcost,1', Rule::exists('fixed_assets', 'id')],
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
            'cellphone_number' => 'nullable|digits_between:11,12',
            'acquisition_details' => 'required|string',
            'brand' => 'nullable',
            'quantity' => 'required|numeric|min:1',
            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, true)],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
//            'account_title_id' => 'required|exists:account_titles,id',
            'letter_of_request' => ['bail', 'nullable', 'required-if:attachment_type,Unbudgeted', 'max:10000', new FileOrX],
            'quotation' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'specification_form' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'tool_of_trade' => ['bail', 'nullable', 'max:10000', new FileOrX],
            'other_attachments' => ['bail','nullable', 'required-if:type_of_request_id,2', 'max:10000', new FileOrX],
            'uom_id' => 'required|exists:unit_of_measures,id',
            'receiving_warehouse_id' => 'required|exists:warehouses,id',
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
            'acquisition_details.required' => 'The acquisition details is required.',
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
            'date_needed.required' => 'The date needed is required.',
            'date_needed.date' => 'The date needed must be a date.',
            'date_needed.after_or_equal' => 'Please select a valid date needed.',
            'cellphone_number.digits_between' => 'Invalid cellphone number',
            'uom_id.required' => 'The unit of measure field is required',
            'uom_id.exists' => 'The selected unit of measure is invalid',
            'letter_of_request.required_if' => 'The letter of request is required.',
            'other_attachments.required_if' => 'The other attachments is required.',
            'receiving_warehouse_id.required' => 'The receiving warehouse field is required',
            'receiving_warehouse_id.exists' => 'The selected receiving warehouse is invalid',
        ];
    }
}
