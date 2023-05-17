<?php

namespace App\Http\Requests\FixedAsset;

use App\Models\Division;
use App\Models\FixedAsset;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use Illuminate\Foundation\Http\FormRequest;

class FixedAssetRequest extends FormRequest
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

        if ($this->isMethod('post')) {
            return [
                'capex' => 'nullable',
                'project_name' => 'required',
                'tag_number' => ['required', function ($attribute, $value, $fail) {
                $tag_number = FixedAsset::withTrashed()->where('tag_number', $value)->exists();
                    if ($tag_number) {
                        $fail('Tag number already exists');
                    }
                }],
                'tag_number_old' => ['required', function ($attribute, $value, $fail) {
                $tag_number_old = FixedAsset::withTrashed()->where('tag_number_old', $value)->exists();
                    if ($tag_number_old) {
                        $fail('Tag number old already exists');
                    }
                }],
                'asset_description' => 'required',
                'type_of_request' => 'required',
                'asset_specification' => 'required',
                'accountability' => 'required',
                'accountable' => 'required',
                'cellphone_number' => 'nullable|numeric',
                'brand' => 'nullable',
                'division' => 'required|exists:divisions,division_name',
                'major_category' => 'required|exists:major_categories,major_category_name',
                'minor_category' => 'required|exists:minor_categories,minor_category_name',
                'voucher' => 'nullable',
                'receipt' => 'nullable',
                'quantity' => 'required',
                'depreciation_method' => 'required',
                'est_useful_life' => ['required', 'numeric', 'max:100'],
                'acquisition_date' => ['required', 'date_format:Y-m-d', 'date'],
                'acquisition_cost' => ['required', 'numeric'],
                'scrap_value' => ['required','numeric'],
                'original_cost' => ['required','numeric'],
                'accumulated_cost' => ['required','numeric'],
                'status' => 'required|boolean',
                'care_of' => 'required',
                'age' => 'required|numeric',
                'end_depreciation' => 'required|date_format:Y-m',
                'depreciation_per_year' => ['required','numeric'],
                'depreciation_per_month' => ['required','numeric'],
                'remaining_book_value' => ['required','numeric'],
                'start_depreciation' => ['required','numeric'],
                'company_code' => 'required|exists:companies,company_code',
                'company' => 'required|exists:companies,company_name',
                'department_code' => 'required|exists:departments,department_code',
                'department' => 'required|exists:departments,department_name',
                'location_code' => 'required|exists:locations,location_code',
                'location' => 'required|exists:locations,location_name',
                'account_code' => 'required|exists:account_titles,account_title_code',
                'account_title' => 'required|exists:account_titles,account_title_name',
            ];
        }
    }

    function messages()
    {
        return [
            'capex.required' => 'Capex is required',
            'project_name.required' => 'Project name is required',
            'tag_number.required' => 'Tag number is required',
            'tag_number_old.required' => 'Tag number old is required',
            'asset_description.required' => 'Asset description is required',
            'type_of_request.required' => 'Type of request is required',
            'asset_specification.required' => 'Asset specification is required',
            'accountability.required' => 'Accountability is required',
            'accountable.required' => 'Accountable is required',
            'cellphone_number.numeric' => 'Cellphone number must be a number',
            'brand.required' => 'Brand is required',
            'division.required' => 'Division is required',
            'major_category.required' => 'Major category is required',
            'minor_category.required' => 'Minor category is required',
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
            'status.required' => 'Status is required',
            'status.boolean' => 'Status must be a boolean',
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
