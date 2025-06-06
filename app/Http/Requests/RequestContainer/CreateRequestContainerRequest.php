<?php

namespace App\Http\Requests\RequestContainer;

use App\Http\Requests\BaseRequest;
use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\RequestContainer;
use App\Models\SubUnit;
use App\Models\TypeOfRequest;
use App\Rules\FileOrX;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use App\Rules\SmallToolRequired;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
                Rule::exists('type_of_requests', 'id'),
                function ($attribute, $value, $fail) {
                    $assetContainer = RequestContainer::where('requester_id', auth()->user()->id)
                        ->whereHas('typeOfRequest', function ($query) {
                            $query->whereIn('type_of_request_name', ['Small Tools', 'Small Tool']);
                        })->where('item_status', 'Replacement')->first();

                    if (!$assetContainer) {
                        return;
                    }

                    //From Container
                    $companyId = $assetContainer->company_id;
                    $businessUnitId = $assetContainer->business_unit_id;
                    $departmentId = $assetContainer->department_id;
                    $unitId = $assetContainer->unit_id;
                    $subunitId = $assetContainer->subunit_id;
                    $locationId = $assetContainer->location_id;

                    //From New Request
                    $nrCompanyId = request()->company_id;
                    $nrBusinessUnitId = request()->business_unit_id;
                    $nrDepartmentId = request()->department_id;
                    $nrUnitId = request()->unit_id;
                    $nrSubunitId = request()->subunit_id;
                    $nrLocationId = request()->location_id;

                    // Check if they do not match
                    if (
                        $companyId != $nrCompanyId ||
                        $businessUnitId != $nrBusinessUnitId ||
                        $departmentId != $nrDepartmentId ||
                        $unitId != $nrUnitId ||
                        $subunitId != $nrSubunitId ||
                        $locationId != $nrLocationId
                    ) {
                        $fail('This COA did not match the COA from the previous replacement small tools');
                    }
                }
            ],
            'attachment_type' => 'required|in:Budgeted,Unbudgeted',
            'capex_number' => 'nullable',
            'is_addcost' => 'nullable|boolean',
            'item_status' => 'nullable  |in:New,Replacement,Additional',
//            'small_tool_id' => [
//                'required_if:type_of_request_id,' . TypeOfRequest::where('type_of_request_name', 'Small Tools')->first()->id,
//                'exists:small_tools,id',
//            ],
            /*'small_tool_id' => [
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
            ],*/
            'item_id' => ['nullable',
                'exists:asset_small_tools,id', function ($attribute, $value, $fail) {
                    $fixedAsset = FixedAsset::where('id', request()->fixed_asset_id)->first();
                    //available items
                    $availableItems = $fixedAsset->assetSmallTools->where('status_description', 'Good')->where('id', $value)->first()->quantity ?? 0;

                    if ($availableItems == 0) {
                        $fail('The selected item is already Requested or Not Available');
                    }
                    //check the quantity of the $availableItems, if the available item has 2 quantity, then the user can't request for the same item more than 2 time or have the quantity of more than 2
                    $itemCount = RequestContainer::where('item_id', $value)
                        ->where('fixed_asset_id', $fixedAsset->id)
                        ->get()
                        ->sum('quantity');

                    $requestItemCount = AssetRequest::where('item_id', $value)
                        ->where('fixed_asset_id', $fixedAsset->id)
                        ->where(function($query) {
                            $query->where('status', '!=', 'Cancelled')
                                ->orWhere('filter', '!=', 'Claimed');
                        })
                        ->get()
                        ->sum('quantity');

                    $totalItemCountInRequest = $itemCount + $requestItemCount;
                    $totalItemQuantity = $totalItemCountInRequest + request()->quantity;

                    if ($totalItemQuantity > $availableItems) {
                        $fail('The selected item is already Requested or Not Available');
                    }


                }],
            'fixed_asset_id' => ['nullable', function ($attribute, $value, $fail) {
                //if the request item_id is not null then skip this validation
                if ($this->input('item_id') !== null) {
//                    $fail('test');
                    return;
                }
//                $fail('success');
                $fixedAsset = FixedAsset::where('id', $value)->first();

                $faContainerCheck = RequestContainer::where('fixed_asset_id', $value)->count();
                $faRequestCheck = AssetRequest::where('fixed_asset_id', $value)
                    ->where(function($query) {
                        $query->where('status', '!=', 'Cancelled')
                            ->orWhere('filter', '!=', 'Claimed');
                    })->count();


                if($faContainerCheck || $faRequestCheck){
                    $fail('The selected fixed asset is already requested.');
                }
            }],
            /*            'fixed_asset_id' => [
                            'required-if:item_status,Replacement',
            //                Rule::exists('fixed_assets', 'id'),
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
                        ],*/

            'accountability' => 'required|in:Personal Issued,Common',
            'accountable' => [
                'required_if:accountability,Personal Issued',
                function ($attribute, $value, $fail) {
                    $accountable = request()->input('accountable');
                    //if the accountability is not Personal Issued, nullify the accountable field and return
                    if (request()->accountability !== 'Personal Issued') {
                        request()->merge(['accountable' => null]);
                        return;
                    }
                },
            ],
            'additional_info' => 'required',
            'acquisition_details' => 'required|string',
            'asset_description' => 'required',
            'asset_specification' => 'nullable',
            'cellphone_number' => 'nullable|digits_between:11,12',
            'brand' => 'nullable',
            'quantity' => 'required|numeric|min:1',
            'date_needed' => 'required|date|after_or_equal:today',
            'letter_of_request' => ['nullable', 'required-if:attachment_type,Unbudgeted', 'max:10000', new FileOrX],
            'quotation' => ['nullable', 'max:10000', new FileOrX],
            'specification_form' => ['nullable', 'max:10000', new FileOrX],
            'tool_of_trade' => ['nullable', 'max:10000', new FileOrX],
            'other_attachments' => ['nullable', 'required-if:type_of_request_id,2', 'max:10000', new FileOrX],
            'major_category_id' => 'nullable|exists:major_categories,id',
            'minor_category_id' => 'nullable|exists:minor_categories,id',
            'company_id' => 'nullable|exists:companies,id',
            'business_unit_id' => ['nullable', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['nullable', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id), function ($attribute, $value, $fail) {
                $department = Department::where('id', $value)->first();
                //check if the location has a warehouse
                if ($department->receivingWarehouse == null) {
                    $fail('The selected location does not have a warehouse.');
                }
            }],
            'unit_id' => ['nullable', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['nullable', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, true)],
            'location_id' => ['nullable', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
//            'account_title_id' => 'nullable|exists:account_titles,id',
            'initial_debit_id' => 'required|exists:account_titles,sync_id',
            'depreciation_credit_id' => 'required|exists:account_titles,sync_id',
            'uom_id' => 'required|exists:unit_of_measures,id',
//            'receiving_warehouse_id' => 'required|exists:warehouses,sync_id',
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
            'additional_info.required' => 'The capex number/unit charging field is required',
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
            'cellphone_number.digits_between' => 'Invalid cellphone number.',
            'letter_of_request.required_if' => 'The letter of request is required.',
            'other_attachments.required_if' => 'The other attachments is required.',
            'small_tool_id.exists' => 'The small tool is invalid.',
            'small_tool_id.required_if' => 'The small tool is required.',
        ];
    }

}
