<?php

namespace App\Http\Requests\AdditionalCost;

use App\Models\AdditionalCost;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\Status\DepreciationStatus;
use App\Models\SubUnit;
use App\Models\Unit;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Carbon\Carbon;
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
        if ($this->isMethod('post')) {
            return $this->getArr();
        }

        if ($this->isMethod('put') && ($this->route()->parameter('additional_cost'))) {
            $id = $this->route()->parameter('additional_cost');
            return [
//                'fixed_asset_id' => 'required|exists:fixed_assets,id',
                'po_number' => 'nullable',
//                'rr_number' => 'required',
                'asset_description' => 'required',
                'type_of_request_id' => 'required',
                'asset_specification' => 'required',
                'accountability' => 'required',
                'accountable' => [
                    'required_if:accountability,Personal Issued',
                    function ($attribute, $value, $fail) {
                        $accountable = request()->input('accountable');
                        //if the value of accountability is not Personal Issued, continue
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
                'voucher' => ['nullable',
//                    function ($attribute, $value, $fail) {
//                    if (request()->depreciation_method != 'Supplier Rebase') {
//                        //if the depreciation status is running depreciation and fully depreciated required voucher
//                        $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
//                        if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
//                            if (in_array($value, [null, '-'])) {
//                                $fail('Voucher is required');
//                                return;
//                            }
//                        if ($value == '-') {
////                            $fail('Voucher is required');
//                            return;
//                        }
//                        $voucher = FixedAsset::where('voucher', $value)->first();
//                        if ($voucher) {
//                            $uploaded_date = Carbon::parse($voucher->created_at)->format('Y-m-d');
//                            $current_date = Carbon::now()->format('Y-m-d');
//                            if ($uploaded_date != $current_date) {
//                                $fail("Voucher previously uploaded.");
//                            }
//                        }
//                        }
//                    }
//                }
                ],
                'voucher_date' => ['nullable',
                    function ($attribute, $value, $fail) {
//                        if (request()->depreciation_method != 'Supplier Rebase') {
//                            //if the depreciation status is running depreciation and fully depreciated required voucher
//                            $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
//                            if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
//                                //get the value of the voucher
//                                if (in_array($value, [null, '-'])) {
//                                    $fail('Voucher date is required');
//                                    return;
//                                }
//
//                                $voucher = request()->voucher;
//
//                                $fa_voucher_date = FixedAsset::where('voucher', $voucher)->first()->voucher_date ?? null;
//                                $ac_voucher_date = AdditionalCost::where('voucher', $voucher)->first()->voucher_date ?? null;
//
//                                if (isset($fa_voucher_date) && ($fa_voucher_date != $value)) {
//                                    $fail('Same voucher with different date found');
//                                }
//                                if (isset($ac_voucher_date) && ($ac_voucher_date != $value)) {
//                                    $fail('Same voucher with different date found');
//                                }
//                            }
//                        }
                    }
                ],
//                'supplier_id' => 'nullable|exists:suppliers,id',
//                'requester_id' => 'nullable|exists:users,id',
                'receipt' => 'nullable',
                'quantity' => 'required',
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
                }],
                'scrap_value' => ['required', 'numeric', function ($attribute, $value, $fail) {
                    if ($value < 0) {
                        $fail('Invalid scrap value');
                    }
                    //if scrap value is geater than acquisition cost fail
                    if ($value > request()->acquisition_cost) {
                        $fail('Must not be greater than acquisition cost');
                    }
                    if (request()->depreciation_method == 'Supplier Rebase') {
                        if ($value != 0) {
                            $fail('Scrap value should be 0');
                        }
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
                    if ($depreciation_status->depreciation_status_name == 'For Depreciation') {
                        if ($value != 0) {
                            $fail('Months depreciated should be 0');
                        }
                    }
                }],
                'release_date' => ['nullable', 'date_format:Y-m-d'],
//                'start_depreciation' => ['required', 'date_format:Y-m'],
                'company_id' => 'required|exists:companies,id',
                'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
                'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
                'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
                'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, false)],
                'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
                'account_title_id' => 'required|exists:account_titles,id',
                'uom_id' => 'nullable|exists:unit_of_measures,id',
            ];
        }
        if ($this->isMethod('patch') && ($this->route()->parameter('id'))) {
            $id = $this->route()->parameter('id');
            return [
                'status' => 'required|boolean',
//                'remarks' => 'required_if:status,false|string|max:255',
            ];
        }
    }

    function messages()
    {
        return [
            'fixed_asset_id.required' => 'The fixed asset is required.',
            'fixed_asset_id.exists' => 'Please select a valid fixed asset',
            'asset_description.required' => 'The asset description is required.',
            'type_of_request_id.required' => 'The type of request id is required.',
            'asset_specification.required' => 'The asset specification is required.',
            'accountability.required' => 'The accountability is required.',
            'accountable.required_if' => 'The accountable is required if accountability is Personal Issued.',
            'cellphone_number.numeric' => 'The cellphone number must be a number.',
            'cellphone_number.digits' => 'The cellphone number must be 11 digits.',
            'brand.required' => 'The brand is required.',
            'major_category_id.required' => 'The major category is required.',
            'major_category_id.exists' => 'The major category must be a valid major category.',
            'minor_category_id.required' => 'The minor category is required.',
            'minor_category_id.exists' => 'The minor category must be a valid minor category.',
            'voucher.required' => 'The voucher is required.',
            'receipt.required' => 'The receipt is required.',
            'quantity.required' => 'The quantity is required.',
            'asset_status_id.required' => 'The asset status is required.',
            'asset_status_id.exists' => 'The asset status must be a valid asset status.',
            'depreciation_status_id.required' => 'The depreciation status is required.',
            'depreciation_status_id.exists' => 'The depreciation status must be a valid depreciation status.',
            'cycle_count_status_id.required' => 'The cycle count status is required.',
            'cycle_count_status_id.exists' => 'The cycle count status must be a valid cycle count status.',
            'movement_status_id.required' => 'The movement status is required.',
            'movement_status_id.exists' => 'The movement status must be a valid movement status.',
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
            'department_id.required' => 'The department is required.',
            'department_id.exists' => 'The department must be a valid department.',
            'account_title_id.required' => 'The account title is required.',
            'account_title_id.exists' => 'The account title must be a valid account title.',

            'status.required' => 'The status is required.',
            'status.boolean' => 'The status must be a boolean.',
            'remarks.required_if' => 'The remarks is required.',
            'remarks.string' => 'The remarks must be a string.',
            'remarks.max' => 'The remarks may not be greater than 255 characters.',
            'uom_id' => 'The unit of measure is required.',
            'uom_id.exists' => 'The unit of measure must be a valid unit of measure.',

        ];
    }

    /**
     * @return array
     */
    public function getArr(): array
    {
        return [
            'po_number' => 'nullable',
            'fixed_asset_id' => 'required|exists:fixed_assets,id',
            'asset_description' => 'required',
            'type_of_request_id' => 'required',
            'asset_specification' => 'required',
            'accountability' => 'required',
            'accountable' => [
                'required_if:accountability,Personal Issued',
                function ($attribute, $value, $fail) {
                    $accountable = request()->input('accountable');
                    //if accountable is null continue
                    if ($value == null) {
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
            'voucher' => ['nullable',
//                function ($attribute, $value, $fail) {
//                    if (request()->depreciation_method != 'Supplier Rebase') {
//                        //if the depreciation status is running depreciation and fully depreciated required voucher
//                        $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
//                        if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
//
//                            if (in_array($value, [null, '-'])) {
//                                $fail('Voucher is required');
//                                return;
//                            }
////                    if ($value == '-') {
//////                            $fail('Voucher is required');
////                        return;
////                    }
////                    $voucher = FixedAsset::where('voucher', $value)->first();
////                    if ($voucher) {
////                        $uploaded_date = Carbon::parse($voucher->created_at)->format('Y-m-d');
////                        $current_date = Carbon::now()->format('Y-m-d');
////                        if ($uploaded_date != $current_date) {
////                            $fail("Voucher previously uploaded.");
////                        }
////                    }
//                        }
//                    }
//                }
            ],
            'voucher_date' => ['nullable',
//                function ($attribute, $value, $fail) {
//                    if (request()->depreciation_method != 'Supplier Rebase') {
//                        //if the depreciation status is running depreciation and fully depreciated required voucher
//                        $depreciation_status = DepreciationStatus::where('id', request()->depreciation_status_id)->first();
//                        if ($depreciation_status->depreciation_status_name == 'Running Depreciation' || $depreciation_status->depreciation_status_name == 'Fully Depreciated') {
//                            //get the value of the voucher
//                            if (in_array($value, [null, '-'])) {
//                                $fail('Voucher date is required');
//                                return;
//                            }
//
//                            $voucher = request()->voucher;
//
//                            $fa_voucher_date = FixedAsset::where('voucher', $voucher)->first()->voucher_date ?? null;
//                            $ac_voucher_date = AdditionalCost::where('voucher', $voucher)->first()->voucher_date ?? null;
//
//                            if (isset($fa_voucher_date) && ($fa_voucher_date != $value)) {
//                                $fail('Same voucher with different date found');
//                            }
//                            if (isset($ac_voucher_date) && ($ac_voucher_date != $value)) {
//                                $fail('Same voucher with different date found');
//                            }
//                        }
//                    }
//                }
            ],
            'receipt' => 'nullable',
            'quantity' => 'required',
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
            }],
            'scrap_value' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Invalid scrap value');
                }
                if ($value > request()->acquisition_cost) {
                    $fail('Must not be greater than acquisition cost');
                }
                if (request()->depreciation_method == 'Supplier Rebase') {
                    if ($value != 0) {
                        $fail('Scrap value should be 0');
                    }
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
                if ($depreciation_status->depreciation_status_name == 'For Depreciation') {
                    if ($value != 0) {
                        $fail('Months depreciated should be 0');
                    }
                }
            }],
            'release_date' => ['nullable', 'date_format:Y-m-d'],
//                'start_depreciation' => ['required', 'date_format:Y-m'],
            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, false)],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
            'account_title_id' => 'required|exists:account_titles,id',
            'uom_id' => 'nullable|exists:unit_of_measures,id',
        ];
    }
}
