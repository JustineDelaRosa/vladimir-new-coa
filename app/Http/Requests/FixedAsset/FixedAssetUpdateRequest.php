<?php

namespace App\Http\Requests\FixedAsset;

use App\Models\FixedAsset;
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
            'capex_id' => 'nullable|exists:capexes,id',
            'project_name' => ['nullable', function ($attribute, $value, $fail) {
                $capex = \App\Models\Capex::find(request()->capex_id);
                if ($capex->project_name != $value) {
                    $fail('Invalid project name');
                }
            }],
            'tag_number' => ['nullable', 'max:13', function ($attribute, $value, $fail) use ($id) {
                //if the value id "-" and the is_old_asset is true return fail error
                if($value == "-" && $this->is_old_asset){
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
            'asset_description' => 'required',
            'type_of_request_id' => 'required',
            'asset_specification' => 'required',
            'accountability' => 'required',
            'accountable' => ['required',function ($attribute, $value, $fail) {
                $accountability = request()->input('accountable');
                $fullIdNumber = $accountability['general_info']['full_id_number'];
                //change the value of accountable to full id number
                request()->merge(['accountable' => $fullIdNumber]);

                // Example validation:
                if (!$fullIdNumber) {
                    $fail('Full ID number is required');
                }
            }],
            'cellphone_number' => 'nullable|numeric|digits:11',
            'brand' => 'required',
            'division_id' => 'required|exists:divisions,id',
            'major_category_id' => 'required|exists:major_categories,id',
            'minor_category_id' => 'required|exists:minor_categories,id',
            'voucher' => 'nullable',
            'receipt' => 'nullable',
            'quantity' => 'required',
            //if any of tag_number and tag_number_old is not null, then is_old_asset is true else false
            'is_old_asset' =>  ['required','boolean', function ($attribute, $value, $fail) {
                if ($value == 1) {
                    if (request()->tag_number == null && request()->tag_number_old == null) {
                        $fail('Either tag number or tag number old is required');
                    }
                }
            }],
            'faStatus' => 'required|in:Good,For Disposal,For Repair,Spare,Sold,Write Off',
            'depreciation_method' => 'required',
            'est_useful_life' => ['required', 'numeric', 'max:100'],
            'acquisition_date' => ['required', 'date_format:Y-m-d', 'date'],
            'acquisition_cost' => ['required', 'numeric'],
            'scrap_value' => ['required', 'numeric'],
            'original_cost' => ['required', 'numeric'],
            'accumulated_cost' => ['nullable', 'numeric'],
            'care_of' => 'required',
            'age' => 'required|numeric',
//            'end_depreciation' => 'required|date_format:Y-m',
            'depreciation_per_year' => ['nullable', 'numeric'],
            'depreciation_per_month' => ['nullable', 'numeric'],
            'remaining_book_value' => ['nullable', 'numeric'],
            'release_date' => ['required', 'date_format:Y-m'],
//            'start_depreciation' => ['required', 'date_format:Y-m'],
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'required|exists:departments,id',
            'location_id' => 'required|exists:locations,id',
            'account_title_id' => 'required|exists:account_titles,id',
        ];
    }


    function messages(): array
    {
        return [
            'capex.required' => 'Capex is required',
            'capex.unique' => 'Capex already exists',
            'project_name.required' => 'Project name is required',
            'tag_number.required' => 'Tag number is required',
            'tag_number_old.required' => 'Tag number old is required',
            'asset_description.required' => 'Asset description is required',
            'type_of_request_id.required' => 'Type of request is required',
            'asset_specification.required' => 'Asset specification is required',
            'accountability.required' => 'Accountability is required',
            'accountable.required' => 'Accountable is required',
            'cellphone_number.numeric' => 'Cellphone number must be a number',
            'brand.required' => 'Brand is required',
            'division_id.required' => 'Division is required',
            'division_id.exists' => 'Division does not exist',
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
            'acquisition_cost.required' => 'Acquisition cost is required',
            'acquisition_cost.numeric' => 'Acquisition cost must be a number',
            'scrap_value.required' => 'Scrap value is required',
            'scrap_value.numeric' => 'Scrap value must be a number',
            'original_cost.required' => 'Original cost is required',
            'original_cost.numeric' => 'Original cost must be a number',
            'accumulated_cost.required' => 'Accumulated cost is required',
            'accumulated_cost.numeric' => 'Accumulated cost must be a number',
            'faStatus.required' => 'Status is required',
            'faStatus.boolean' => 'Status must be a boolean',
            'faStatus.in' => 'The selected status is invalid.',
            'care_of.required' => 'Care of is required',
            'age.required' => 'Age is required',
            'age.numeric' => 'Age must be a number',
            'end_depreciation.required' => 'End depreciation is required',
            'end_depreciation.date_format' => 'End depreciation must be a date',
            'depreciation_per_year.required' => 'Depreciation per year is required',
            'depreciation_per_year.numeric' => 'Depreciation per year must be a number',
            'depreciation_per_month.required' => 'Depreciation per month is required',
            'depreciation_per_month.numeric' => 'Depreciation per month must be a number',
            'remaining_book_value.required' => 'Remaining book value is required',
            'remaining_book_value.numeric' => 'Remaining book value must be a number',
            'release_date.required' => 'Released date is required',
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

        ];
    }

}
