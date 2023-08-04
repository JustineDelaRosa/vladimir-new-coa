<?php

namespace App\Http\Requests\AdditionalCost;

use Illuminate\Foundation\Http\FormRequest;

class AdditionalCostRequest extends FormRequest
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
        if($this->isMethod('post')){
            return $this->getArr();
        }

        if($this->isMethod('put') && ($this->route()->parameter('additional_cost'))){
            $id = $this->route()->parameter('additional_cost');
            return [
//                'fixed_asset_id' => 'required|exists:fixed_assets,id',
                'asset_description' => 'required',
                'type_of_request_id' => 'required',
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
                        if (isset($accountability['general_info']['full_id_number_full_name'])) {
                            $full_id_number_full_name = $accountability['general_info']['full_id_number_full_name'];
                            request()->merge(['accountable' => $full_id_number_full_name]);
                        } else {
                            // Fail validation if keys don't exist
                            $fail('The accountable person\'s full name is required.');
                            return;
                        }

                        // Validate full name
                        if ($full_id_number_full_name === '') {
                            $fail('The accountable person\'s full name cannot be empty.');
                            return;
                        }
                    },
                ],
                'cellphone_number' => 'nullable|numeric|digits:11',
                'brand' => 'nullable',
                'major_category_id' => 'required|exists:major_categories,id',
                'minor_category_id' => 'required|exists:minor_categories,id',
                'voucher' => 'nullable',
                'receipt' => 'nullable',
                'quantity' => 'required',
                'asset_status_id' => 'required|exists:asset_statuses,id',
                'depreciation_status_id' => 'required|exists:depreciation_statuses,id',
                'cycle_count_status_id' => 'required|exists:cycle_count_statuses,id',
                'movement_status_id' => 'required|exists:movement_statuses,id',
                'depreciation_method' => 'required',
                'acquisition_date' => ['required', 'date_format:Y-m-d', 'date'],
                'acquisition_cost' => ['required', 'numeric'],
                'scrap_value' => ['required', 'numeric'],
                'depreciable_basis' => ['required', 'numeric'],
                'accumulated_cost' => ['nullable', 'numeric'],
                'care_of' => 'nullable',
                'months_depreciated' => 'required|numeric',
                'depreciation_per_year' => ['nullable', 'numeric'],
                'depreciation_per_month' => ['nullable', 'numeric'],
                'remaining_book_value' => ['nullable', 'numeric'],
                'release_date' => ['required', 'date_format:Y-m-d'],
                'department_id' => 'required|exists:departments,id',
                'account_title_id' => 'required|exists:account_titles,id',
            ];
        }
        if($this->isMethod('patch') && ($this->route()->parameter('id'))){
            $id = $this->route()->parameter('id');
            return[
                'status' => 'required|boolean',
                'remarks' => 'required_if:status,false|string|max:255',
            ];
        }
    }

    function messages()
    {
        return[
            'fixed_asset_id.required' => 'The fixed asset id is required.',
            'fixed_asset_id.exists' => 'The fixed asset id must be a valid fixed asset id.',
            'asset_description.required' => 'The asset description is required.',
            'type_of_request_id.required' => 'The type of request id is required.',
            'asset_specification.required' => 'The asset specification is required.',
            'accountability.required' => 'The accountability is required.',
            'accountable.required_if' => 'The accountable is required if accountability is Personal Issued.',
            'cellphone_number.numeric' => 'The cellphone number must be a number.',
            'cellphone_number.digits' => 'The cellphone number must be 11 digits.',
            'brand.required' => 'The brand is required.',
            'major_category_id.required' => 'The major category id is required.',
            'major_category_id.exists' => 'The major category id must be a valid major category id.',
            'minor_category_id.required' => 'The minor category id is required.',
            'minor_category_id.exists' => 'The minor category id must be a valid minor category id.',
            'voucher.required' => 'The voucher is required.',
            'receipt.required' => 'The receipt is required.',
            'quantity.required' => 'The quantity is required.',
            'asset_status_id.required' => 'The asset status id is required.',
            'asset_status_id.exists' => 'The asset status id must be a valid asset status id.',
            'depreciation_status_id.required' => 'The depreciation status id is required.',
            'depreciation_status_id.exists' => 'The depreciation status id must be a valid depreciation status id.',
            'cycle_count_status_id.required' => 'The cycle count status id is required.',
            'cycle_count_status_id.exists' => 'The cycle count status id must be a valid cycle count status id.',
            'movement_status_id.required' => 'The movement status id is required.',
            'movement_status_id.exists' => 'The movement status id must be a valid movement status id.',
            'depreciation_method.required' => 'The depreciation method is required.',
            'acquisition_date.required' => 'The acquisition date is required.',
            'acquisition_date.date_format' => 'The acquisition date must be a valid date format.',
            'acquisition_date.date' => 'The acquisition date must be a valid date.',
            'acquisition_cost.required' => 'The acquisition cost is required.',
            'acquisition_cost.numeric' => 'The acquisition cost must be a number.',
            'scrap_value.required' => 'The scrap value is required.',
            'scrap_value.numeric' => 'The scrap value must be a number.',
            'depreciable_basis.required' => 'The depreciable basis is required.',
            'depreciable_basis.numeric' => 'The depreciable basis must be a number.',
            'accumulated_cost.numeric' => 'The accumulated cost must be a number.',
            'care_of.required' => 'The care of is required.',
            'months_depreciated.required' => 'The months depreciated is required.',
            'months_depreciated.numeric' => 'The months depreciated must be a number.',
            'depreciation_per_year.numeric' => 'The depreciation per year must be a number.',
            'depreciation_per_month.numeric' => 'The depreciation per month must be a number.',
            'remaining_book_value.numeric' => 'The remaining book value must be a number.',
            'release_date.required' => 'The release date is required.',
            'release_date.date_format' => 'The release date must be a valid date format.',
            'department_id.required' => 'The department id is required.',
            'department_id.exists' => 'The department id must be a valid department id.',
            'account_title_id.required' => 'The account title id is required.',
            'account_title_id.exists' => 'The account title id must be a valid account title id.',

            'status.required' => 'The status is required.',
            'status.boolean' => 'The status must be a boolean.',
            'remarks.required_if' => 'The remarks is required.',
            'remarks.string' => 'The remarks must be a string.',
            'remarks.max' => 'The remarks may not be greater than 255 characters.',

        ];
    }

    /**
     * @return array
     */
    public function getArr(): array
    {
        return [
            'fixed_asset_id' => 'required|exists:fixed_assets,id',
            'asset_description' => 'required',
            'type_of_request_id' => 'required',
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
                    if (isset($accountability['general_info']['full_id_number_full_name'])) {
                        $full_id_number_full_name = $accountability['general_info']['full_id_number_full_name'];
                        request()->merge(['accountable' => $full_id_number_full_name]);
                    } else {
                        // Fail validation if keys don't exist
                        $fail('The accountable person\'s full name is required.');
                        return;
                    }

                    // Validate full name
                    if ($full_id_number_full_name === '') {
                        $fail('The accountable person\'s full name cannot be empty.');
                        return;
                    }
                },
            ],
            'cellphone_number' => 'nullable|numeric|digits:11',
            'brand' => 'nullable',
            'major_category_id' => 'required|exists:major_categories,id',
            'minor_category_id' => 'required|exists:minor_categories,id',
            'voucher' => 'nullable',
            'receipt' => 'nullable',
            'quantity' => 'required',
            'asset_status_id' => 'required|exists:asset_statuses,id',
            'depreciation_status_id' => 'required|exists:depreciation_statuses,id',
            'cycle_count_status_id' => 'required|exists:cycle_count_statuses,id',
            'movement_status_id' => 'required|exists:movement_statuses,id',
            'depreciation_method' => 'required',
            'acquisition_date' => ['required', 'date_format:Y-m-d', 'date'],
            'acquisition_cost' => ['required', 'numeric'],
            'scrap_value' => ['required', 'numeric'],
            'depreciable_basis' => ['required', 'numeric'],
            'accumulated_cost' => ['nullable', 'numeric'],
            'care_of' => 'nullable',
            'months_depreciated' => 'required|numeric',
            'depreciation_per_year' => ['nullable', 'numeric'],
            'depreciation_per_month' => ['nullable', 'numeric'],
            'remaining_book_value' => ['nullable', 'numeric'],
            'release_date' => ['required', 'date_format:Y-m-d'],
            'department_id' => 'required|exists:departments,id',
            'account_title_id' => 'required|exists:account_titles,id',
        ];
    }
}
