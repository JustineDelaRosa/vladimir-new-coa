<?php

namespace App\Imports;

use App\Models\AccountTitle;
use App\Models\AdditionalCost;
use App\Models\Capex;
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
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use App\Repositories\AdditionalCostRepository;
use App\Repositories\CalculationRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
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

        if ($cell->getColumn() == 'Q') {
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
            'end_depreciation' => $this->calculationRepository->getEndDepreciation(substr_replace($collection['start_depreciation'], '-', 4, 0), $est_useful_life, $collection['depreciation_method']),
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
            'department_id' => Department::where('department_code', $collection['department_code'])->first()->id,
            'location_id' => Location::where('location_code', $collection['location_code'])->first()->id,
            'account_id' => AccountTitle::where('account_title_code', $collection['account_code'])->first()->id,
        ]);
    }


    private function rules($collections)
    {
        $processedFixedAssets = [];
        return [
            '*.vladimir_tag_number' => ['required', 'exists:fixed_assets,vladimir_tag_number'],
            '*.description' => 'required',
            '*.type_of_request' => 'required|exists:type_of_requests,type_of_request_name',
            '*.additional_description' => 'required',
            '*.accountability' => 'required',
            '*.accountable' => ['required_if:*.accountability,Personal Issued',
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections));
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
            '*.minor_category' => ['required', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections));
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

            }],

            '*.voucher' => [
                'required',
//                function ($attribute, $value, $fail) use ($collections, &$processedFixedAssets) {
//                    $index = array_search($attribute, array_keys($collections));
//                    $vladimirTagNumber = $collections[$index]['vladimir_tag_number'];
//                    $fixedAsset = FixedAsset::where('vladimir_tag_number', $vladimirTagNumber)->first();
//                    $fixedAssetId = $fixedAsset->id ?? 0;
//                    if (isset($processedFixedAssets[$fixedAssetId][$value])) {
//                        $fail('Duplicate voucher');
//                        return;
//                    }
//                    $processedFixedAssets[$fixedAssetId][$value] = true;
//                    $additionalCost = AdditionalCost::where('fixed_asset_id', $fixedAssetId)
//                        ->where('voucher', $value)
//                        ->first();
//                    if ($additionalCost) {
//                        $fail('Voucher already exists');
//                    }
//                }
            ],
            '*.receipt' => 'required',
            '*.quantity' => 'required|numeric',
            '*.depreciation_method' => 'required|in:STL,One Time',
            '*.acquisition_date' => ['required', 'string', 'date_format:Y-m-d', 'date', 'before_or_equal:today'],
            '*.acquisition_cost' => ['required', 'numeric', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections));
                $scrap_value = $collections[$index]['scrap_value'];

                if ($value < $scrap_value) {
                    $fail('Acquisition cost must not be less than scrap value');
                }

                if ($value < 0) {
                    $fail('Acquisition cost must not be negative');
                }
            }],
            '*.scrap_value' => ['required',],
            '*.depreciable_basis' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Depreciation basis must not be negative');
                }
            }],
            '*.accumulated_cost' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Accumulated cost must not be negative');
                }
            }],
            '*.asset_status' => [
                'required',
                Rule::exists('asset_statuses', 'asset_status_name')->whereNull('deleted_at'),
            ],
            '*.depreciation_status' => [
                'required',
                Rule::exists('depreciation_statuses', 'depreciation_status_name')->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections));
                    //allow only fully depreciated and running depreciation
                    $depreciation = DepreciationStatus::where('depreciation_status_name', $value)->first();
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
            '*.end_depreciation' => ['required', function($attribute, $value, $fail) use ($collections){
                if(strlen($value) !== 6){
                    $fail('Invalid end depreciation format');
                    return;
                }
                $index = array_search($attribute, array_keys($collections));
                $depreciation_status_name = $collections[$index]['depreciation_status'];
                $depreciation_status = DepreciationStatus::where('depreciation_status_name', $depreciation_status_name)->first();
                if($depreciation_status->depreciation_status_name == 'Fully Depreciated'){
                    //check if the value of end depreciation is not yet passed the current date (yyyymm)
                    $current_date = Carbon::now()->format('Y-m');
                    $value = substr_replace($value, '-', 4, 0);
                    //check if the value is parsable or not
                    if(Carbon::parse($value)->isAfter($current_date)){
                        $fail('Not yet fully depreciated');
                    }
                } elseif($depreciation_status->depreciation_status_name == 'Running Depreciation'){
                    //check if the value of end depreciation is not yet passed the current date (yyyymm)
                    $current_date = Carbon::now()->format('Y-m');
                    $value = substr_replace($value, '-', 4, 0);
                    //check if the value is parsable or not
                    if(Carbon::parse($value)->isBefore($current_date)){
                        $fail('The asset is fully depreciated');
                    }
                }
            }],
            '*.depreciation_per_year' => ['required'],
            '*.depreciation_per_month' => ['required'],
            '*.remaining_book_value' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Remaining book value must not be negative');
                }
            }],
            '*.start_depreciation' => ['required'],
            '*.company_code' => ['required', 'exists:companies,company_code', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections));
                $company_name = $collections[$index]['company'];
                $company = Company::query()
                    ->where('company_code', $value)
                    ->where('company_name', $company_name)
                    ->where('is_active', '!=', 0)
                    ->first();
                if (!$company) {
                    $fail('Invalid company');
                }
            }],
            '*.department_code' => ['required', 'exists:departments,department_code', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections));
                $company_code = $collections[$index]['company_code'];
                $company_sync_id = Company::where('company_code', $company_code)->first()->sync_id ?? 0;
                $department = Department::where('department_code', $value)
                    ->where('company_sync_id', $company_sync_id)
                    ->first();
                if (!$department) {
                    $fail('Invalid department, company combination');
                }
            }],
            '*.location_code' => ['required', 'exists:locations,location_code', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections));
                //check if the code is correct on the database
                $location_name = $collections[$index]['location'];
                $location = Location::query()
                    ->where('location_code', $value)
                    ->where('location_name', $location_name)
                    ->where('is_active', '!=', 0)
                    ->first();
                if (!$location) {
                    $fail('Invalid location');
                    return;
                }
                $department_code = $collections[$index]['department_code'];
                $department_sync_id = Department::where('department_code', $department_code)->first()->sync_id ?? 0;
                $associated_location_sync_id = $location->departments->pluck('sync_id')->toArray();
                if (!in_array($department_sync_id, $associated_location_sync_id)) {
                    $fail('Invalid location, department combination');
                }

            }],
            '*.account_code' => ['required', 'exists:account_titles,account_title_code', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections));
                $account_title_name = $collections[$index]['account_title'];
                $account_title = AccountTitle::query()
                    ->where('account_title_code', $value)
                    ->where('account_title_name', $account_title_name)
                    ->where('is_active', '!=', 0)
                    ->first();
                if (!$account_title) {
                    $fail('Invalid account title');
                }
            }],
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
