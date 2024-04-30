<?php

namespace App\Http\Requests\FixedAsset;

use App\Models\AdditionalCost;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\Status\DepreciationStatus;
use App\Models\SubCapex;
use App\Models\SubUnit;
use App\Models\TypeOfRequest;
use App\Models\Unit;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class FixedAssetUpdateRequest extends FormRequest
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
        $id = $this->route()->parameter('fixed_asset');
        return [
            'po_number' => 'required',
//            'rr_number' => 'required',
            'sub_capex_id' => ['nullable', function ($attribute, $value, $fail) {
                // Get the type_of_request_id from request
                $type_of_request_id = request()->input('type_of_request_id');
//                $sub_capex = SubCapex::where('id', $value)->exists();
//                if(!$sub_capex){
//                    $fail('Sub capex does not exist');
//                }
                // Fetch the TypeOfRequest object based on the id
                $typeOFRequest = TypeOfRequest::find($type_of_request_id);

                // If the sub capex is '-' and the type of request is not 'Capex', then do nothing and let the validation continue
                // If the conditions are not met, the validation will fail
                if ($value == '-' && ucwords(strtolower($typeOFRequest->type_of_request_name)) != 'Capex') {
                    //make it null
                    request()->merge([$attribute => null]);
                }
            }],
            'tag_number' => ['nullable', 'max:13', function ($attribute, $value, $fail) use ($id) {
                //if the value id "-" and the is_old_asset is true return fail error
                if ($value == "-" && $this->is_old_asset) {
                    $fail('This is required for old asset');
                }
                $tag_number = FixedAsset::withTrashed()->where('tag_number', $value)
                    ->where('tag_number', '!=', '-')
                    ->where('id', '!=', $id)
                    ->exists();
                if ($tag_number) {
                    $fail('Tag number already exists');
                }
            }],
            'tag_number_old' => ['nullable', 'max:13', function ($attribute, $value, $fail) use ($id) {
                $tag_number_old = FixedAsset::withTrashed()->where('tag_number_old', $value)
                    ->where('tag_number_old', '!=', '-')
                    ->where('id', '!=', $id)
                    ->exists();
                if ($tag_number_old) {
                    $fail('Tag number old already exists');
                }
            }],
//            'supplier_id' => 'nullable|exists:suppliers,id',
//            'requester_id' => 'nullable|exists:users,id',
            'asset_description' => 'required',
            'type_of_request_id' => 'required',
//            'charged_department' => 'required',
            'asset_specification' => 'required',
            'accountability' => 'required',
            'accountable' => [
                'required_if:accountability,Personal Issued',
                function ($attribute, $value, $fail) {
                    $accountable = request()->input('accountable');
                    if (request()->accountability != 'Personal Issued') {
                        request()->merge(['accountable' => null]);
                        return;
                    }

                    // Check if necessary keys exist to avoid undefined index
                    if (isset($accountable['general_info']['full_id_number_full_name'])) {
                        $full_id_number_full_name = $accountable['general_info']['full_id_number_full_name'];
                        request()->merge(['accountable' => $full_id_number_full_name]);
                    } else {
                        // Fail validation if keys don't exist
                        $fail('The accountable person is required.');
                        return;
                    }

                    // Validate full name
                    if ($full_id_number_full_name === '') {
                        $fail('The accountable person cannot be empty.');
                    }
                },
            ],
            'cellphone_number' => 'nullable|numeric|digits:11',
            'brand' => 'nullable',
            'major_category_id' => 'required|exists:major_categories,id',
            'minor_category_id' => 'required|exists:minor_categories,id',
            'voucher' => [function ($attribute, $value, $fail) {
                if (request()->depreciation_method != 'Supplier Rebase') {
                    //if the depreciation status is running depreciation and fully depreciated required voucher
                    $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                    if ($depreciation_status == null) {
//                        $fail('Please select a valid depreciation status');
                        return;
                    }
                    if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
                        if (in_array($value, [null, '-'])) {
                            $fail('Voucher is required');
                            return;
                        }

//                    if ($value == '-') {
////                            $fail('Voucher is required');
//                        return;
//                    }
//                    $voucher = FixedAsset::where('voucher', $value)->first();
//                    if ($voucher) {
//                        $uploaded_date = Carbon::parse($voucher->created_at)->format('Y-m-d');
//                        $current_date = Carbon::now()->format('Y-m-d');
//                        if ($uploaded_date != $current_date) {
//                            $fail('Voucher previously uploaded.');
//                        }
//                    }
                    }
                }

            }],
            'voucher_date' => ['nullable',
//                function ($attribute, $value, $fail) {
//                if (request()->depreciation_method != 'Supplier Rebase') {
//                    //if the depreciation status is running depreciation and fully depreciated required voucher
//                    $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
//                    if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
//                        //get the value of the voucher
//                        if (in_array($value, [null, '-'])) {
//                            $fail('Voucher date is required');
//                            return;
//                        }
//
//                        $voucher = request()->voucher;
//
//                        $fa_voucher_date = FixedAsset::where('voucher', $voucher)->first()->voucher_date ?? null;
//                        $ac_voucher_date = AdditionalCost::where('voucher', $voucher)->first()->voucher_date ?? null;
//
//                        if (isset($fa_voucher_date) && ($fa_voucher_date != $value)) {
//                            $fail('Same voucher with different date found');
//                        }
//                        if (isset($ac_voucher_date) && ($ac_voucher_date != $value)) {
//                            $fail('Same voucher with different date found');
//                        }
//                    }
//                }
//            }
            ],
            'receipt' => ['nullable', function ($attribute, $value, $fail) {
                //if the depreciation status is running depreciation and fully depreciated required voucher
                $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                if ($depreciation_status == null) {
//                            $fail('Please select a valid depreciation status');
                    return;
                }
                if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
                    if (in_array($value, [null, '-'])) {
                        $fail('Receipt is required');
                    }
                }

            }],
            'quantity' => 'required',
            //if any of tag_number and tag_number_old is not null, then is_old_asset is true else false
            'is_old_asset' => ['required', 'boolean', function ($attribute, $value, $fail) {
                if ($value == 1) {
                    if (request()->tag_number == null && request()->tag_number_old == null) {
                        $fail('Either tag number or tag number old is required');
                    }
                }
            }],
            'asset_status_id' => 'required|exists:asset_statuses,id',
            'depreciation_status_id' => 'required|exists:depreciation_statuses,id',
            'cycle_count_status_id' => 'required|exists:cycle_count_statuses,id',
            'movement_status_id' => 'required|exists:movement_statuses,id',
            'depreciation_method' => 'required',
            'acquisition_date' => ['required', 'date_format:Y-m-d', 'date', 'before_or_equal:today'],
            //acquisition cost should not be less than or equal to 0
            'acquisition_cost' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if (request()->depreciation_method == 'Supplier Rebase') {
                    if ($value != 0) {
                        $fail('Acquisition cost should be 0');
                    }
                }
                if ($value < 0) {
                    $fail('Invalid acquisition cost');
                }
                $major_category = request()->major_category_id;
                $major_category = MajorCategory::where('id', $major_category)->first();
                if (!$major_category) {
//                    $fail('Please select a valid Major Category');
                    return;
                }
                if ($major_category->est_useful_life == 0 || $major_category->est_useful_life == 0.0) {
                    request()->merge(['acquisition_cost' => 0]);
                }
            }],
            'scrap_value' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Invalid scrap value');
                }

                if (request()->depreciation_method == 'Supplier Rebase') {
                    if ($value != 0) {
                        $fail('Scrap value should be 0');
                    }
                }
                $major_category = request()->major_category_id;
                $major_category = MajorCategory::where('id', $major_category)->first();
                if (!$major_category) {
//                    $fail('Please select a valid Major Category');
                    return;
                }
                if ($major_category->est_useful_life == 0 || $major_category->est_useful_life == 0.0) {
                    request()->merge(['scrap_value' => 0]);
                    return;
                }
                if ($value > request()->acquisition_cost) {
                    $fail('Must not be greater than acquisition cost');
                }
            }],
            'depreciable_basis' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if (request()->depreciation_method == 'Supplier Rebase') {
                    if ($value != 0) {
                        $fail('Depreciable basis should be 0');
                    }
                }
                if ($value < 0) {
                    $fail('Invalid depreciable basis');
                }
                $major_category = request()->major_category_id;
                $major_category = MajorCategory::where('id', $major_category)->first();
                if (!$major_category) {
//                    $fail('Please select a valid Major Category');
                    return;
                }
                if ($major_category->est_useful_life == 0 || $major_category->est_useful_life == 0.0) {
                    request()->merge(['depreciable_basis' => 0]);
                }
            }],
//                'accumulated_cost' => ['nullable', 'numeric'],
            'care_of' => 'nullable',
            'months_depreciated' => ['required', 'numeric', function ($attribute, $value, $fail) {

                //    if depreciation method is Supplier Rebase, and no more months depreciated acquisition cost, scrap value and depreciable basis
                if (request()->depreciation_method == 'Supplier Rebase') {
                    if ($value != 0) {
                        $fail('Months depreciated should be 0');
                    }
                }

                //get what is the depreciation status is for depreciation
                $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                if($depreciation_status == null){
//                    $fail('Please select a valid depreciation status');
                    return;
                }
                if ($depreciation_status->depreciation_status_name == 'For Depreciation') {
                    if ($value != 0) {
                        $fail('Months depreciated should be 0');
                    }
                }
                $major_category = request()->major_category_id;
                $major_category = MajorCategory::where('id', $major_category)->first();
                if (!$major_category) {
//                    $fail('Please select a valid Major Category');
                    return;
                }
                if ($major_category->est_useful_life == 0 || $major_category->est_useful_life == 0.0) {
                    request()->merge(['months_depreciated' => 0]);
                }
            }],

            'release_date' => ['nullable', 'date_format:Y-m-d',
//                function ($attribute, $value, $fail) {
//                //get what is the depreciation status is for depreciation
//                $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
//                if ($depreciation_status && $depreciation_status->depreciation_status_name == 'For Depreciation') {
//                    if ($value != null || $value != '') {
//                        $fail('Release date should be empty for depreciation status \'For Depreciation\'');
//                    }
//                    request()->merge([$attribute => null]); // Set the release_date attribute to null
//                }else{
//                    if ($value == null || $value == '') {
//                        $fail('Release date is required');
//                    }
//                }
//            }
            ],
//                'start_depreciation' => ['required', 'date_format:Y-m'],
            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, false)],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
//            'print_count' => 'nullable|numeric',
            'uom_id' => 'required|exists:unit_of_measures,id',
        ];
    }


    function messages(): array
    {
        return [
            'sub_capex_id.exists' => 'Sub capex is invalid',
            'tag_number.required' => 'Tag number is required',
            'tag_number.max' => 'Tag number must not exceed 13 characters',
            'tag_number_old.required' => 'Tag number old is required',
            'tag_number_old.max' => 'Tag number old must not exceed 13 characters',
            'asset_description.required' => 'Asset description is required',
            'type_of_request_id.required' => 'Type of request is required',
            'asset_specification.required' => 'Asset specification is required',
            'accountability.required' => 'Accountability is required',
            'accountable.required_if' => 'Accountable is required',
            'cellphone_number.numeric' => 'Cellphone number must be numeric',
            'cellphone_number.digits' => 'Cellphone number must be 11 digits',
            'major_category_id.required' => 'Major category is required',
            'minor_category_id.required' => 'Minor category is required',
            'quantity.required' => 'Quantity is required',
            'is_old_asset.required' => 'Is old asset is required',
            'asset_status_id.required' => 'Asset status is required',
            'depreciation_status_id.required' => 'Depreciation status is required',
            'cycle_count_status_id.required' => 'Cycle count status is required',
            'movement_status_id.required' => 'Movement status is required',
            'depreciation_method.required' => 'Depreciation method is required',
            'acquisition_date.required' => 'Acquisition date is required',
            'acquisition_date.date_format' => 'Acquisition date must be in Y-m-d format',
            'acquisition_date.date' => 'Acquisition date must be a valid date',
            'acquisition_cost.required' => 'Acquisition cost is required',
            'acquisition_cost.numeric' => 'Acquisition cost must be numeric',
            'scrap_value.required' => 'Scrap value is required',
            'scrap_value.numeric' => 'Scrap value must be numeric',
            'depreciable_basis.required' => 'Depreciable basis is required',
            'depreciable_basis.numeric' => 'Depreciable basis must be numeric',
            'accumulated_cost.numeric' => 'Accumulated cost must be numeric',
            'months_depreciated.required' => 'Months depreciated is required',
            'months_depreciated.numeric' => 'Months depreciated must be numeric',
            'end_depreciation.required' => 'End depreciation is required',
            'end_depreciation.date_format' => 'End depreciation must be in Y-m format',
            'depreciation_per_year.numeric' => 'Depreciation per year must be numeric',
            'depreciation_per_month.numeric' => 'Depreciation per month must be numeric',
            'remaining_book_value.numeric' => 'Remaining book value must be numeric',
            'release_date.required' => 'Release date is required',
            'release_date.date_format' => 'Invalid release date format',
            'start_depreciation.required' => 'Start depreciation is required',
            'start_depreciation.date_format' => 'Start depreciation must be in Y-m format',
            'department_id.required' => 'Department is required',
            'account_title_id.required' => 'Account title is required',
            'uom_id.required' => 'Unit of measure is required',
            'uom_id.exists' => 'Unit of measure is invalid',
        ];
    }

}
