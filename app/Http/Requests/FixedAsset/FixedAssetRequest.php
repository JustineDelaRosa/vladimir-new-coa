<?php

namespace App\Http\Requests\FixedAsset;

use App\Models\Capex;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\Status\DepreciationStatus;
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use Illuminate\Foundation\Http\FormRequest;

class FixedAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
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
        //Adding of Fixed Asset
        if ($this->isMethod('post') &&
            ($this->sub_capex_id === null)) {
            return [
//              'capex_id' => 'nullable',
                'sub_capex_id' => 'nullable',
                'tag_number' => ['nullable', 'max:13', function ($attribute, $value, $fail) {
                    //if the value id "-" and the is_old_asset is true return fail error
                    if ($value == "-" && $this->is_old_asset) {
                        $fail('This is required for old asset');
                    }
                    $tag_number = FixedAsset::withTrashed()->where('tag_number', $value)
                        ->where('tag_number', '!=', '-')
                        ->exists();
                    if ($tag_number) {
                        $fail('Tag number already exists');
                    }
                }],
                'tag_number_old' => ['nullable', 'max:13', function ($attribute, $value, $fail) {
                    $tag_number_old = FixedAsset::withTrashed()->where('tag_number_old', $value)
                        ->where('tag_number_old', '!=', '-')
                        ->exists();
                    if ($tag_number_old) {
                        $fail('Tag number old already exists');
                    }
                }],
                'asset_description' => 'required',
                'type_of_request_id' => 'required',
//                'charged_department' => 'required',
                'asset_specification' => 'required',
                'accountability' => 'required',
                'accountable' => [
                    'required_if:accountability,Personal Issued',
                    function ($attribute, $value, $fail) {
                        $accountability = request()->input('accountable');
                        //if accountable is null continue
                        if ($value == null) {
                            return;
                        }

                        // Check if necessary keys exist to avoid undefined index
                        if (isset($accountability['general_info']['full_id_number'])) {
                            $full_id_number = $accountability['general_info']['full_id_number'];
                            request()->merge(['accountable' => $full_id_number]);
                        } else {
                            // Fail validation if keys don't exist
                            $fail('The accountable person\'s full name is required.');
                            return;
                        }

                        // Validate full name
                        if ($full_id_number === '') {
                            $fail('The accountable person\'s full name cannot be empty.');
                        }
                    },
                ],
                'cellphone_number' => 'nullable|numeric|digits:11',
                'brand' => 'nullable',
                'major_category_id' => 'required|exists:major_categories,id',
                'minor_category_id' => 'required|exists:minor_categories,id',
                'voucher' => ['nullable', function($attribute, $value, $fail){
                    //if the depreciation status is running depreciation and fully depreciated required voucher
                    $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                    if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
                        if ($value == null) {
                            $fail('Voucher is required');
                        }
                    }

                }],
                'receipt' => ['nullable', function($attribute, $value, $fail){
                    //if the depreciation status is running depreciation and fully depreciated required voucher
                    $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                    if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
                        if ($value == null) {
                            $fail('Voucher is required');
                        }
                    }

                }],
                'quantity' => 'required',
                'asset_status_id' => 'required|exists:asset_statuses,id',
                'depreciation_status_id' => 'required|exists:depreciation_statuses,id',
                'cycle_count_status_id' => 'required|exists:cycle_count_statuses,id',
                'movement_status_id' => 'required|exists:movement_statuses,id',
                'is_old_asset' => ['required', 'boolean', function ($attribute, $value, $fail) {
                    if ($value == 1) {
                        if (request()->tag_number == null && request()->tag_number_old == null) {
                            $fail('Either tag number or tag number old is required');
                        }
                    }
                }],
                'depreciation_method' => 'required',
                'acquisition_date' => ['required', 'date_format:Y-m-d', 'date','before_or_equal:today'],
                //acquisition cost should not be less than or equal to 0
                'acquisition_cost' => ['required', 'numeric', function ($attribute, $value, $fail) {
                    if (request()->depreciation_method == 'Donated') {
                        if ($value != 0) {
                            $fail('Acquisition cost should be 0');
                        }
                    }
                    if ($value <= 0) {
                        $fail('Invalid acquisition cost');
                    }
                }],
                'scrap_value' => ['required', 'numeric', function ($attribute, $value, $fail){
                    if (request()->depreciation_method == 'Donated') {
                        if ($value != 0) {
                            $fail('Scrap value should be 0');
                        }
                    }
                }],
                'depreciable_basis' => ['required', 'numeric',function ($attribute, $value, $fail) {
                    if (request()->depreciation_method == 'Donated') {
                        if ($value != 0) {
                            $fail('Depreciable basis should be 0');
                        }
                    }
                    if ($value <= 0) {
                        $fail('Invalid depreciable basis');
                    }
                }],
//                'accumulated_cost' => ['nullable', 'numeric'],
                'care_of' => 'nullable',
                'months_depreciated' => ['required', 'numeric', function ($attribute, $value, $fail) {

                    //    if depreciation method is Donated, and no more months depreciated acquisition cost, scrap value and depreciable basis
                    if (request()->depreciation_method == 'Donated') {
                        if ($value != 0) {
                            $fail('Months depreciated should be 0');
                        }
                    }

                    //get what is the depreciation status is for depreciation
                    $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                    if ($depreciation_status->depreciation_status_name == 'For Depreciation') {
                        if ($value != 0) {
                            $fail('Months depreciated should be 0');
                        }
                    }




                }],
//                'end_depreciation' => 'required|date_format:Y-m',
//                'depreciation_per_year' => ['nullable', 'numeric'],
//                'depreciation_per_month' => ['nullable', 'numeric'],
//                'remaining_book_value' => ['nullable', 'numeric'],
                'release_date' => ['nullable','date_format:Y-m-d'],
//                'start_depreciation' => ['required', 'date_format:Y-m'],
                'department_id' => 'required|exists:departments,id',
                'location_id' => [
                    'required',
                    'exists:locations,id',
                    function ($attribute, $value, $fail) {
                        // Fetch the location and associated departments only once
                        $location = Location::query()->find($value);

                        // Check if the location is active
                        if (!$location || !$location->is_active) {
                            $fail('Location is not active or does not exist.');
                            return; // No point in proceeding if the location is not active
                        }

                        // Get the sync_id of the department
                        $department_sync_id = Department::query()->where('id', request()->department_id)->value('sync_id');

                        // Get sync_id's of all locations associated with the department
                        $associated_location_sync_ids = $location->departments->pluck('sync_id');
//                        dd($associated_location_sync_ids);
                        // Check if department's sync_id exists in associated_location_sync_ids
                        if (!$associated_location_sync_ids->contains($department_sync_id)) {
                            $fail('Invalid location for the department');
                        }
                    }
                ],
                'account_title_id' => 'required|exists:account_titles,id',
            ];
        }

        if ($this->isMethod('post')) {
            return [
//                'capex_id' => 'required|exists:capexes,id',
                'sub_capex_id' => ['required', 'exists:sub_capexes,id'
//                    ,function ($attribute, $value, $fail) {
//                    $typeOfRequest = TypeOfRequest::query();
//                    $typeOfRequest->where('id', request()->type_of_request_id)->first()->type_of_request_name;
//                    $capex = SubCapex::withTrashed()->where('capex_id', request()->capex_id)->where('id', $value)->first();
//                    if (!$capex) {
//                        $fail('Invalid sub capex selected');
//                        return;
//                    }
//                    if ($capex->deleted_at) {
//                        $fail('Sub capex is already deleted');
//                    }
//                }
                ],
                'tag_number' => ['nullable', 'max:13', function ($attribute, $value, $fail) {
                    //if the value id "-" and the is_old_asset is true return fail error
                    if ($value == "-" && $this->is_old_asset) {
                        $fail('This is required for old asset');
                    }
                    $tag_number = FixedAsset::withTrashed()->where('tag_number', $value)
                        ->where('tag_number', '!=', '-')
                        ->exists();
                    if ($tag_number) {
                        $fail('Tag number already exists');
                    }
                }],
                'tag_number_old' => ['nullable', 'max:13', function ($attribute, $value, $fail) {
                    $tag_number_old = FixedAsset::withTrashed()->where('tag_number_old', $value)
                        ->where('tag_number_old', '!=', '-')
                        ->exists();
                    if ($tag_number_old) {
                        $fail('Tag number old already exists');
                    }
                }],
                'asset_description' => 'required',
                'type_of_request_id' => 'required',
//                'charged_department' => 'required',
                'asset_specification' => 'required',
                'accountability' => 'required',
                'accountable' => [
                    'required_if:accountability,Personal Issued',
                    function ($attribute, $value, $fail) {
                        $accountability = request()->input('accountable');
                        //if accountable is null continue
                        if ($value == null) {
                            return;
                        }

                        // Check if necessary keys exist to avoid undefined index
                        if (isset($accountability['general_info']['full_id_number'])) {
                            $full_id_number = $accountability['general_info']['full_id_number'];
                            request()->merge(['accountable' => $full_id_number]);
                        } else {
                            // Fail validation if keys don't exist
                            $fail('The accountable person\'s full name is required.');
                            return;
                        }

                        // Validate full name
                        if ($full_id_number === '') {
                            $fail('The accountable person\'s full name cannot be empty.');
                        }
                    },
                ],
                'cellphone_number' => 'nullable|numeric|digits:11',
                'brand' => 'nullable',
                'major_category_id' => 'required|exists:major_categories,id',
                'minor_category_id' => 'required|exists:minor_categories,id',
                'voucher' => ['nullable', function($attribute, $value, $fail){
                    //if the depreciation status is running depreciation and fully depreciated required voucher
                    $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                    if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
                        if ($value == null) {
                            $fail('Voucher is required');
                        }
                    }

                }],
                'receipt' => ['nullable', function($attribute, $value, $fail){
                    //if the depreciation status is running depreciation and fully depreciated required voucher
                    $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                    if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
                        if ($value == null) {
                            $fail('Voucher is required');
                        }
                    }

                }],
                'quantity' => 'required',
                'asset_status_id' => 'required|exists:asset_statuses,id',
                'depreciation_status_id' => 'required|exists:depreciation_statuses,id',
                'cycle_count_status_id' => 'required|exists:cycle_count_statuses,id',
                'movement_status_id' => 'required|exists:movement_statuses,id',
                'is_old_asset' => ['required', 'boolean', function ($attribute, $value, $fail) {
                    if ($value == 1) {
                        if (request()->tag_number == null && request()->tag_number_old == null) {
                            $fail('Either tag number or tag number old is required');
                        }
                    }
                }],
                'depreciation_method' => 'required',
                'acquisition_date' => ['required', 'date_format:Y-m-d', 'date','before_or_equal:today'],
                //acquisition cost should not be less than or equal to 0
                'acquisition_cost' => ['required', 'numeric', function ($attribute, $value, $fail) {
                    if (request()->depreciation_method == 'Donated') {
                        if ($value != 0) {
                            $fail('Acquisition cost should be 0');
                        }
                    }
                    if ($value <= 0) {
                        $fail('Invalid acquisition cost');
                    }
                }],
                'scrap_value' => ['required', 'numeric', function ($attribute, $value, $fail){
                    if (request()->depreciation_method == 'Donated') {
                        if ($value != 0) {
                            $fail('Scrap value should be 0');
                        }
                    }
                }],
                'depreciable_basis' => ['required', 'numeric',function ($attribute, $value, $fail) {
                    if (request()->depreciation_method == 'Donated') {
                        if ($value != 0) {
                            $fail('Depreciable basis should be 0');
                        }
                    }
                    if ($value <= 0) {
                        $fail('Invalid depreciable basis');
                    }
                }],
//                'accumulated_cost' => ['nullable', 'numeric'],
                'care_of' => 'nullable',
                'months_depreciated' => ['required', 'numeric', function ($attribute, $value, $fail) {

                    //    if depreciation method is Donated, and no more months depreciated acquisition cost, scrap value and depreciable basis
                    if (request()->depreciation_method == 'Donated') {
                        if ($value != 0) {
                            $fail('Months depreciated should be 0');
                        }
                    }

                    //get what is the depreciation status is for depreciation
                    $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
                    if ($depreciation_status->depreciation_status_name == 'For Depreciation') {
                        if ($value != 0) {
                            $fail('Months depreciated should be 0');
                        }
                    }
                }],
                'release_date' => ['nullable','date_format:Y-m-d'],
//                'start_depreciation' => ['required', 'date_format:Y-m'],
                'department_id' => 'required|exists:departments,id',
                'location_id' => [
                    'required',
                    'exists:locations,id',
                    function ($attribute, $value, $fail) {
                        // Fetch the location and associated departments only once
                        $location = Location::query()->find($value);

                        // Check if the location is active
                        if (!$location || !$location->is_active) {
                            $fail('Location is not active or does not exist.');
                            return; // No point in proceeding if the location is not active
                        }

                        // Get the sync_id of the department
                        $department_sync_id = Department::query()->where('id', request()->department_id)->value('sync_id');

                        // Get sync_id's of all locations associated with the department
                        $associated_location_sync_ids = $location->departments->pluck('sync_id');
//                        dd($associated_location_sync_ids);
                        // Check if department's sync_id exists in associated_location_sync_ids
                        if (!$associated_location_sync_ids->contains($department_sync_id)) {
                            $fail('Invalid location for the department');
                        }
                    }
                ],
                'account_title_id' => 'required|exists:account_titles,id',
            ];
        }

        if($this->isMethod('patch') && ($this->route()->parameter('id'))){
            $id = $this->route()->parameter('id');
            return[
              'status' => 'required|boolean',
//                'remarks' => 'required_if:status,false|string|max:255',
            ];
        }
    }

    /**
     * Custom error messages
     *
     * @return array
     */
    function messages(): array
    {
        return [
            'capex_id.required' => 'Capex is required',
            'capex_id.exists' => 'Capex does not exist',
            'sub_capex_id.required' => 'Sub capex is required',
            'sub_project.required' => 'Sub project is required',
            'project_name.required' => 'Project name is required',
            'project_name.exists' => 'Project name does not exist',
            'tag_number.required' => 'Tag number is required',
            'tag_number.max' => 'Tag number must not exceed 13 characters',
            'tag_number_old.max' => 'Tag number old must not exceed 13 characters',
            'tag_number_old.required' => 'Tag number old is required',
            'asset_description.required' => 'Asset description is required',
            'type_of_request_id.required' => 'Type of request is required',
            'asset_specification.required' => 'Asset specification is required',
            'accountability.required' => 'Accountability is required',
            'accountable.required_if' => 'Accountable is required',
            'cellphone_number.numeric' => 'Cellphone number must be a number',
            'brand.required' => 'Brand is required',
            'major_category_id.required' => 'Major category is required',
            'major_category_id.exists' => 'Major category does not exist',
            'minor_category_id.required' => 'Minor category is required',
            'minor_category_id.exists' => 'Minor category does not exist',
            'voucher.required' => 'Voucher is required',
            'receipt.required' => 'Receipt is required',
            'quantity.required' => 'Quantity is required',
            'depreciation_method.required' => 'Depreciation method is required',
            'est_useful_life.required' => 'Estimated useful life is required',
            'est_useful_life.numeric' => 'Estimated useful life must be a number',
            'est_useful_life.max' => 'Estimated useful life must not exceed 100',
            'acquisition_date.required' => 'Acquisition date is required',
            'acquisition_date.date_format' => 'Acquisition date must be a date',
            'acquisition_date.before_or_equal' => 'Acquisition date must not be past the date today',
            'acquisition_cost.required' => 'Acquisition cost is required',
            'acquisition_cost.numeric' => 'Acquisition cost must be a number',
            'scrap_value.required' => 'Scrap value is required',
            'scrap_value.numeric' => 'Scrap value must be a number',
            'depreciable_basis.required' => 'Depreciable basis is required',
            'depreciable_basis.numeric' => 'Depreciable basis must be a number',
            'accumulated_cost.required' => 'Accumulated cost is required',
            'accumulated_cost.numeric' => 'Accumulated cost must be a number',
            'asset_status.required' => 'Status is required',
            'asset_status.in' => 'The selected status is invalid.',
            'depreciation_status.required' => 'Depreciation status is required',
            'depreciation_status.in' => 'The selected status is invalid.',
            'cycle_count_status.required' => 'Cycle count status is required',
            'cycle_count_status.in' => 'The selected status is invalid.',
            'movement_status.required' => 'Movement status is required',
            'movement_status.in' => 'The selected status is invalid.',
            'care_of.required' => 'Care of is required',
            'months_depreciated.required' => 'Months depreciated is required',
            'months_depreciated.numeric' => 'Months depreciated must be a number',
            'end_depreciation.required' => 'End depreciation is required',
            'end_depreciation.date_format' => 'End depreciation must be a date',
            'depreciation_per_year.required' => 'Depreciation per year is required',
            'depreciation_per_year.numeric' => 'Depreciation per year must be a number',
            'depreciation_per_month.required' => 'Depreciation per month is required',
            'depreciation_per_month.numeric' => 'Depreciation per month must be a number',
            'remaining_book_value.required' => 'Remaining book value is required',
            'remaining_book_value.numeric' => 'Remaining book value must be a number',
            'release_date.required' => 'Release date is required',
            'release_date.date_format' => 'Invalid release date format',
            'start_depreciation.required' => 'Start depreciation is required',
            'start_depreciation.numeric' => 'Start depreciation must be a number',
            'company_code.required' => 'Company code is required',
            'company_code.exists' => 'Company code does not exist',
            'company.required' => 'Company is required',
            'company.exists' => 'Company does not exist',
            'department_code.required' => 'Department code is required',
            'department_code.exists' => 'Department code does not exist',
            'department.required' => 'Department is required',
            'department.exists' => 'Department does not exist',
            'location_code.required' => 'Location code is required',
            'location_code.exists' => 'Location code does not exist',
            'location.required' => 'Location is required',
            'location.exists' => 'Location does not exist',
            'account_code.required' => 'Account code is required',
            'account_code.exists' => 'Account code does not exist',
            'account_title.required' => 'Account title is required',
            'account_title.exists' => 'Account title does not exist',

            'status.required' => 'Status is required',
            'status.boolean' => 'Status must be a boolean',
            'remarks.required_if' => 'Remarks is required',
            'remarks.string' => 'Remarks must be a string',
            'remarks.max' => 'Remarks must not exceed 255 characters',

        ];
    }
}
