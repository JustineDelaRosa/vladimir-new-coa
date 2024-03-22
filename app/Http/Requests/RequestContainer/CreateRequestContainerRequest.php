<?php

namespace App\Http\Requests\RequestContainer;

use App\Http\Requests\BaseRequest;
use App\Models\Approvers;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\RequestContainer;
use App\Models\SubUnit;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateRequestContainerRequest extends BaseRequest
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
            'additional_info' => 'nullable',
            'acquisition_details' => 'required|string',
            'asset_description' => 'required',
            'asset_specification' => 'nullable',
            'cellphone_number' => 'nullable|numeric',
            'brand' => 'nullable',
            'quantity' => 'required|numeric|min:1',
            'date_needed' => 'required|date|after_or_equal:today',
            'letter_of_request' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10000',
            'quotation' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10000',
            'specification_form' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10000',
            'tool_of_trade' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10000',
            'other_attachments' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10000',
            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id)],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
            'account_title_id' => 'required|exists:account_titles,id',
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
            'additional_info.required' => 'The additional info is required.',
            'acquisition_details.required' => 'The acquisition details is required.',
            'letter_of_request.file' => 'The letter of request must be a file.',
            'letter_of_request.mimes' => 'The letter of request must be a file of type: pdf, doc, docx.',
            'letter_of_request.max' => 'The letter of request may not be greater than 10 megabytes.',
            'quotation.file' => 'The quotation must be a file.',
            'quotation.mimes' => 'The quotation must be a file of type: pdf, doc, docx.',
            'quotation.max' => 'The quotation may not be greater than 10 megabytes.',
            'specification_form.file' => 'The specification form must be a file.',
            'specification_form.mimes' => 'The specification form must be a file of type: pdf, doc, docx.',
            'specification_form.max' => 'The specification form may not be greater than 10 megabytes.',
            'tool_of_trade.file' => 'The tool of trade must be a file.',
            'tool_of_trade.mimes' => 'The tool of trade must be a file of type: pdf, doc, docx.',
            'tool_of_trade.max' => 'The tool of trade may not be greater than 10 megabytes.',
            'other_attachments.file' => 'The other attachments must be a file.',
            'other_attachments.mimes' => 'The other attachments must be a file of type: pdf, doc, docx.',
            'other_attachments.max' => 'The other attachments may not be greater than 10 megabytes.',
            'date_needed.required' => 'The date needed is required.',
            'date_needed.date' => 'The date needed must be a date.',
            'date_needed.after_or_equal' => 'Please select a valid date needed.',

        ];
    }

}
