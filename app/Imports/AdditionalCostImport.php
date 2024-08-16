<?php

namespace App\Imports;

use App\Models\AccountingEntries;
use App\Models\AccountTitle;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\Status\AssetStatus;
use App\Models\Status\CycleCountStatus;
use App\Models\Status\DepreciationStatus;
use App\Models\Status\MovementStatus;
use App\Models\SubUnit;
use App\Models\TypeOfRequest;
use App\Models\Unit;
use App\Repositories\AdditionalCostRepository;
use App\Repositories\CalculationRepository;
use App\Rules\ImportValidation\ValidAccountCode;
use App\Rules\ImportValidation\ValidBusinessUnitCode;
use App\Rules\ImportValidation\ValidCompanyCode;
use App\Rules\ImportValidation\ValidDepartmentCode;
use App\Rules\ImportValidation\ValidLocationCode;
use App\Rules\ImportValidation\ValidSubunitCode;
use App\Rules\ImportValidation\ValidUnitCode;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AdditionalCostImport extends DefaultValueBinder implements
    ToCollection,
    WithHeadingRow,
    WithStartRow,
    WithCustomValueBinder,
    WithCalculatedFormulas
{
    use importable;

    private $calculationRepository, $additionalCostRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
        $this->additionalCostRepository = new AdditionalCostRepository();
    }

    public function startRow(): int
    {
        // TODO: Implement startRow() method.
        return 2;
    }

    public function bindValue(Cell $cell, $value): bool
    {

        if ($cell->getColumn() == 'R') {
            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y-m-d'), DataType::TYPE_STRING);
            return true;
        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }

    /**
     * @param Collection $collection
     * @throws Exception
     */
    public function collection(Collection $collection)
    {
        Validator::make($collection->toArray(), $this->rules($collection->toArray()), $this->messages())->validate();
        foreach ($collection as $collections) {

            $majorCategoryId = $this->getMajorCategoryId($collections['major_category']);
            $minorCategoryId = $this->getMinorCategoryId($collections['minor_category'], $majorCategoryId);
            $est_useful_life = $this->getEstUsefulLife($majorCategoryId);
            $formula = $this->createFormula($collections, $est_useful_life);
            $this->createAdditionalCost($formula, $collections, $majorCategoryId, $minorCategoryId);
        }

    }


    private function getEstUsefulLife($majorCategoryId)
    {
        $majorCategory = MajorCategory::withTrashed()
            ->where('id', $majorCategoryId)
            ->first();

        return $majorCategory ? $majorCategory->est_useful_life : null;
    }

    private function getMajorCategoryId($majorCategoryName)
    {
        $majorCategory = MajorCategory::withTrashed()
            ->where('major_category_name', $majorCategoryName)
            ->first();

        return $majorCategory ? $majorCategory->id : null;
    }

    private function getMinorCategoryId($minorCategoryName, $majorCategoryId)
    {
        $minorCategory = MinorCategory::withTrashed()
            ->where('minor_category_name', $minorCategoryName)
            ->where('major_category_id', $majorCategoryId)
            ->first();
        return $minorCategory ? $minorCategory->id : null;
    }

    private function createFormula($collection, $est_useful_life)
    {
        //current date
        return Formula::create([
            'depreciation_method' => strtoupper($collection['depreciation_method']) == 'STL'
                ? strtoupper($collection['depreciation_method'])
                : ucwords(strtolower($collection['depreciation_method'])),
            'acquisition_date' => $collection['acquisition_date'],
            'acquisition_cost' => $collection['acquisition_cost'],
            'scrap_value' => $collection['scrap_value'],
            'depreciable_basis' => $collection['depreciable_basis'],
            'accumulated_cost' => $collection['accumulated_cost'],
            'months_depreciated' => $this->calculationRepository->getMonthDifference(substr_replace($collection['start_depreciation'], '-', 4, 0), Carbon::now()),
//            'end_depreciation' => Carbon::parse(substr_replace($collection['start_depreciation'], '-', 4, 0))->addYears(floor($est_useful_life))->addMonths(floor(($est_useful_life - floor($est_useful_life)) * 12) - 1)->format('Y-m'),
            'end_depreciation' => substr_replace($collection['end_depreciation'], '-', 4, 0),
            //TODO: The validation is temporarily disabled requested by th proponent.
            //$this->calculationRepository->getEndDepreciation(substr_replace($collection['start_depreciation'], '-', 4, 0), $est_useful_life, $collection['depreciation_method']),
            'depreciation_per_year' => $collection['depreciation_per_year'],
            'depreciation_per_month' => $collection['depreciation_per_month'],
            'remaining_book_value' => $collection['remaining_book_value'],
            'release_date' => Carbon::parse(substr_replace($collection['start_depreciation'], '-', 4, 0))->subMonth()->format('Y-m-d'),
            'start_depreciation' => substr_replace($collection['start_depreciation'], '-', 4, 0),
        ]);
    }


    private function createAdditionalCost($formula, $collection, $majorCategoryId, $minorCategoryId)
    {
        // Check if necessary IDs exist before creating FixedAsset
        if ($majorCategoryId == null || $minorCategoryId == null) {
            throw new Exception('Unable to create FixedAsset due to missing Major/Minor category ID.');
        }

        $accountingEntry = AccountingEntries::create([
            'initial_debit' => 279,
            'initial_credit' => MinorCategory::where('id', $minorCategoryId)->first()->accountTitle->id,
            'depreciation_debit' => 535,
            'depreciation_credit' => 321,
        ]);

        $fixedAssetId = FixedAsset::where('vladimir_tag_number', $collection['vladimir_tag_number'])->first()->id;
        $formula->additionalCost()->create([
            'fixed_asset_id' => $fixedAssetId,
            'add_cost_sequence' => $this->additionalCostRepository->getAddCostSequence($fixedAssetId),
            'asset_description' => ucwords(strtolower($collection['description'])),
            'type_of_request_id' => TypeOfRequest::where('type_of_request_name', ($collection['type_of_request']))->first()->id,
            'asset_specification' => ucwords(strtolower($collection['additional_description'])),
            'accountability' => ucwords(strtolower($collection['accountability'])),
            'accountable' => strtoupper($collection['accountable'] ?? '-'),
            'cellphone_number' => $collection['cellphone_number'],
            'brand' => ucwords(strtolower($collection['brand'])),
            'major_category_id' => $majorCategoryId,
            'minor_category_id' => $minorCategoryId,
            'voucher' => ucwords(strtolower($collection['voucher'])),
            'voucher_date' => $collection['voucher_date'] == '-' ? null : $collection['voucher_date'],
            //check for unnecessary spaces and trim them to one space only
            'receipt' => preg_replace('/\s+/', ' ', ucwords(strtolower($collection['receipt']))),
            'quantity' => $collection['quantity'],
            'depreciation_method' => strtoupper($collection['depreciation_method']) == 'STL'
                ? strtoupper($collection['depreciation_method'])
                : ucwords(strtolower($collection['depreciation_method'])),
            'acquisition_date' => $collection['acquisition_date'],
            'acquisition_cost' => $collection['acquisition_cost'],
            'asset_status_id' => AssetStatus::where('asset_status_name', $collection['asset_status'])->first()->id,
            'cycle_count_status_id' => CycleCountStatus::where('cycle_count_status_name', $collection['cycle_count_status'])->first()->id,
            'depreciation_status_id' => DepreciationStatus::where('depreciation_status_name', $collection['depreciation_status'])->first()->id,
            'movement_status_id' => MovementStatus::where('movement_status_name', $collection['movement_status'])->first()->id,
            'care_of' => ucwords(strtolower($collection['care_of'])),
            'company_id' => Company::where('company_code', $collection['company_code'])->first()->id,
            'business_unit_id' => BusinessUnit::where('business_unit_code', $collection['business_unit_code'])->first()->id,
            'department_id' => Department::where('department_code', $collection['department_code'])->first()->id,
            'unit_id' => Unit::where('unit_code', $collection['unit_code'])->first()->id,
            'subunit_id' => SubUnit::where('sub_unit_code', $collection['subunit_code'])->first()->id,
            'location_id' => Location::where('location_code', $collection['location_code'])->first()->id,
            'account_id' => $accountingEntry,
        ]);
    }


    private function rules($collections)
    {
        $processedFixedAssets = [];
        $collections = collect($collections);
        $index = array_search('capex', array_keys($collections->toArray()));
        return [
            '*.vladimir_tag_number' => ['required', 'exists:fixed_assets,vladimir_tag_number'],
            '*.description' => 'required',
            '*.type_of_request' => 'required|exists:type_of_requests,type_of_request_name',
            '*.additional_description' => 'required',
            '*.accountability' => 'required',
            '*.accountable' => ['required_if:*.accountability,Personal Issued',
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
                    $accountability = $collections[$index]['accountability'];
                    if ($accountability == 'Common') {
                        if ($value != '-') {
                            $fail('Accountable should be empty');
                        }
                    }

                    if ($accountability == 'Personal Issued') {
                        if ($value == '-') {
                            $fail('Accountable is required');
                        }
                    }
//                    $index = array_search($attribute, array_keys($collections));
//                    $vladimirTagNumber = $collections[$index]['vladimir_tag_number'];
//                    $fixedAsset = FixedAsset::where('vladimir_tag_number', $vladimirTagNumber)->first();
//                    $fixedAssetId = $fixedAsset->id ?? 0;
//
//                    // Processing of the fixed assets continues regardless
//                    $processedFixedAssets[$fixedAssetId][$value] = ['vladimir_tag_number' => $vladimirTagNumber];
//
//                    $additionalCost = AdditionalCost::where('fixed_asset_id', $fixedAssetId)
//                        ->where('voucher', $value)
//                        ->first();
//
//                    if ($additionalCost) {
//                        $fail('Voucher already exists with different tag number '. $additionalCost->fixedAsset->vladimir_tag_number);
//                    }
                }
            ],
            '*.cellphone_number' => 'required',
            '*.brand' => 'required',
            '*.major_category' => [
                'required', 'exists:major_categories,major_category_name'],
            '*.minor_category' => ['required',
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
//                $status = $collections[$index]['asset_status'];
                    $major_category = $collections[$index]['major_category'];
                    $major_category = MajorCategory::withTrashed()->where('major_category_name', $major_category)->first()->id ?? 0;
                    $minor_category = MinorCategory::withTrashed()->where('minor_category_name', $value)
                        ->where('major_category_id', $major_category)->first();

//                if($minor_category->trashed()){
//                    $fail('Minor Category does not exist');
//                }
                    if (!$minor_category) {
                        $fail('Minor Category does not exist');
                    }

                }
            ],

            '*.voucher' => ['required',
//                function ($attribute, $value, $fail) {
//                    if ($value == '-') {
////                    $fail('Voucher is required');
//                        return;
//                    }
//                    $voucher = FixedAsset::where('voucher', $value)->first();
//                    //check the created_at if it is the same date with the uploaded date of the voucher if it is the same then it will pass the validation
//                    if ($voucher) {
//                        $uploaded_date = Carbon::parse($voucher->created_at)->format('Y-m-d');
//                        $current_date = Carbon::now()->format('Y-m-d');
//                        if ($uploaded_date != $current_date) {
//                            $fail('Voucher previously uploaded.');
//                        }
//                    }
//
//                }
            ],
            '*.voucher_date' => [
                'required',
//                function ($attribute, $value, $fail) use ($collections) {
//                    $index = array_search($attribute, array_keys($collections));
//                    $voucher = $collections[$index]['voucher'];
//                    if ($voucher == '-') {
//                        if ($value != '-') {
//                            $fail('Voucher date should be empty');
//                            return;
//                        }
//                        return;
//                    }
//                    $this->calculationRepository->validationForDate($attribute, $value, $fail, $collections);
//                }
            ],
            '*.receipt' => 'required',
            '*.quantity' => 'required|numeric',
            '*.depreciation_method' => 'required|in:STL,One Time',
            '*.acquisition_date' => ['required', 'string', 'date_format:Y-m-d', 'date', 'before_or_equal:today'],
            '*.acquisition_cost' => ['required', 'numeric',
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
                    $scrap_value = $collections[$index]['scrap_value'];

                    if ($value < $scrap_value) {
                        $fail('Acquisition cost should exceed scrap value.');
                    }

                    if ($value < 0) {
                        $fail('Acquisition cost must not be negative');
                    }
                }
            ],
            '*.scrap_value' => ['required',],
            '*.depreciable_basis' => ['required', 'numeric',
                function ($attribute, $value, $fail) {
                    if ($value < 0) {
                        $fail('Depreciation basis must not be negative');
                    }
                }
            ],
            '*.accumulated_cost' => ['required', 'numeric',
                function ($attribute, $value, $fail) {
                    if ($value < 0) {
                        $fail('Accumulated cost must not be negative');
                    }
                }
            ],
            '*.asset_status' => [
                'required',
                Rule::exists('asset_statuses', 'asset_status_name')->whereNull('deleted_at'),
            ],
            '*.depreciation_status' => [
                'required',
                Rule::exists('depreciation_statuses', 'depreciation_status_name')->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
                    //allow only fully depreciated and running depreciation
                    $depreciation = DepreciationStatus::where('depreciation_status_name', $value)->first();
                    if (!$depreciation) {
                        $fail('Invalid depreciation status');
                        return;
                    }
                    if ($depreciation->depreciation_status_name != 'Fully Depreciated' && $depreciation->depreciation_status_name != 'Running Depreciation') {
                        $fail('Invalid depreciation status');
                    }

//                    $depreciation_method = $collections[$index]['depreciation_method'];
//                    if ($depreciation_method == 'One Time') {
//                        if ($value != 'Fully Depreciated') {
//                            $fail('Depreciation status should be fully depreciated');
//                        }
//                    }

                }
            ],
            '*.cycle_count_status' => [
                'required',
                Rule::exists('cycle_count_statuses', 'cycle_count_status_name')->whereNull('deleted_at'),
            ],
            '*.movement_status' => [
                'required',
                Rule::exists('movement_statuses', 'movement_status_name')->whereNull('deleted_at'),
            ],
            '*.care_of' => 'required',
            '*.end_depreciation' => ['required',
                function ($attribute, $value, $fail) use ($collections) {
                    $this->calculationRepository->validationForDate($attribute, $value, $fail, $collections);
                }
            ],
            '*.depreciation_per_year' => ['required'],
            '*.depreciation_per_month' => ['required'],
            '*.remaining_book_value' => ['required', 'numeric',
                function ($attribute, $value, $fail) {
                    if ($value < 0) {
                        $fail('Remaining book value must not be negative');
                    }
                }
            ],
            '*.start_depreciation' => ['required',
                function ($attribute, $value, $fail) {
                    $this->calculationRepository->validationForDate($attribute, $value, $fail);
                }
            ],
            '*.company_code' => ['required', new ValidCompanyCode($collections[$index]['company'])],
            '*.business_unit_code' => ['required', new ValidBusinessUnitCode($collections[$index]['company_code'], $collections[$index]['business_unit'])],
            '*.department_code' => ['required', new ValidDepartmentCode($collections[$index]['business_unit_code'], $collections[$index]['department'])],
            '*.unit_code' => ['required', new ValidUnitCode($collections[$index]['department_code'], $collections[$index]['unit'])],
            '*.subunit_code' => ['required', new ValidSubunitCode($collections[$index]['unit_code'], $collections[$index]['subunit'])],
            '*.location_code' => ['required', new ValidLocationCode($collections[$index]['subunit_code'], $collections[$index]['location'])],
            '*.account_code' => ['required', new ValidAccountCode($collections[$index]['account_title'])],
        ];
    }

    private function messages()
    {
        return [
            '*.vladimir_tag_number.required' => 'Vladimir Tag Number is required',
            '*.vladimir_tag_number.exists' => 'Vladimir Tag Number does not exist',
            '*.asset_description.required' => 'Description is required',
            '*.type_of_request.required' => 'Type of Request is required',
            '*.type_of_request.in' => 'Invalid Type of Request',
            '*.additional_description.required' => 'Additional Description is required',
            '*.accountability.required' => 'Accountability is required',
            '*.accountable.required_if' => 'Accountable is required',
            '*.cellphone_number.required' => 'Cellphone Number is required',
            '*.brand.required' => 'Brand is required',
            '*.major_category.required' => 'Major Category is required',
            '*.major_category.exists' => 'Major Category does not exist',
            '*.minor_category.required' => 'Minor Category is required',
            '*.voucher.required' => 'Voucher is required',
            '*.voucher_date.required' => 'Voucher date is required',
            '*.receipt.required' => 'Receipt is required',
            '*.quantity.required' => 'Quantity is required',
            '*.quantity.numeric' => 'Quantity must be a number',
            '*.depreciation_method.required' => 'Depreciation Method is required',
            '*.depreciation_method.in' => 'The selected depreciation method is invalid.',
            '*.acquisition_date.required' => 'Acquisition Date is required',
            '*.acquisition_cost.required' => 'Acquisition Cost is required',
            '*.scrap_value.required' => 'Scrap Value is required',
            '*.depreciable_basis.required' => 'Depreciable basis is required',
            '*.accumulated_cost.required' => 'Accumulated Cost is required',
            '*.asset_status.required' => 'Status is required',
            '*.asset_status.in' => 'The selected status is invalid.',
            '*.depreciation_status.required' => 'Depreciation Status is required',
            '*.depreciation_status.in' => 'The selected depreciation status is invalid.',
            '*.cycle_count_status.required' => 'Cycle Count Status is required',
            '*.cycle_count_status.in' => 'The selected cycle count status is invalid.',
            '*.movement_status.required' => 'Movement Status is required',
            '*.movement_status.in' => 'The selected movement status is invalid.',
            '*.care_of.required' => 'Care Of is required',
            '*.end_depreciation.required' => 'End Depreciation is required',
            '*.depreciation_per_year.required' => 'Depreciation Per Year is required',
            '*.depreciation_per_month.required' => 'Depreciation Per Month is required',
            '*.remaining_book_value.required' => 'Remaining Book Value is required',
            '*.start_depreciation.required' => 'Start Depreciation is required',
            '*.start_depreciation.date_format' => 'Invalid date format',
            '*.company_code.required' => 'Company Code is required',
            '*.company_code.exists' => 'Company Code does not exist',
            '*.department_code.required' => 'Department Code is required',
            '*.department_code.exists' => 'Department Code does not exist',
            '*.location_code.required' => 'Location Code is required',
            '*.location_code.exists' => 'Location Code does not exist',
            '*.account_code.required' => 'Account Code is required',
            '*.account_code.exists' => 'Account Code does not exist',
        ];
    }
}
