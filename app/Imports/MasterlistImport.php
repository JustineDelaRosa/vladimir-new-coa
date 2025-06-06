<?php

namespace App\Imports;

use App\Models\AccountingEntries;
use App\Models\AccountTitle;
use App\Models\BusinessUnit;
use App\Models\Capex;
use App\Models\Company;
use App\Models\Credit;
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
use App\Models\SubUnit;
use App\Models\TypeOfRequest;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Repositories\CalculationRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use App\Rules\ImportValidation\ValidBusinessUnitCode;
use App\Rules\ImportValidation\ValidCompanyCode;
use App\Rules\ImportValidation\ValidDepartmentCode;
use App\Rules\ImportValidation\ValidLocationCode;
use App\Rules\ImportValidation\ValidSubunitCode;
use App\Rules\ImportValidation\ValidUnitCode;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class MasterlistImport extends DefaultValueBinder implements
    ToCollection,
    WithHeadingRow,
    WithCustomValueBinder,
    WithStartRow,
    WithCalculatedFormulas
{
    use Importable;

    private $calculationRepository, $vladimirTagGeneratorRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
        $this->vladimirTagGeneratorRepository = new VladimirTagGeneratorRepository();
    }

    function headingRow(): int
    {
        return 1;
    }

    public function startRow(): int
    {
        return 2;
    }

    /**
     * @throws Exception
     */
    public function bindValue(Cell $cell, $value): bool
    {

        if ($cell->getColumn() == 'W') {
            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y-m-d'), DataType::TYPE_STRING);
            return true;
        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }

    /**
     * @throws Exception
     * @throws ValidationException
     */
    public function collection(Collection $collections)
    {

//        dd($collections);
        /*        dd($collections);

                $client = new Client();
                $token = '9|u27KMjj3ogv0hUR8MMskyNmhDJ9Q8IwUJRg8KAZ4';
                $response = $client->request('GET', 'http://rdfsedar.com/api/data/employees', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ],
                ]);

        // Get the body content from the response
                $body = $response->getBody()->getContents();

        // Decode the JSON response into an associative array
                $data = json_decode($body, true);
                $nameToCheck = [
                    'Perona, jerome',
                    'Dela Rosa, Justine',
                    'Nucum, Caren'
                ];

                if (!empty($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $employee) {
                        if (!empty($employee['general_info']) && in_array($employee['general_info']['full_name'], $nameToCheck)) {
                            echo $employee['general_info']['full_id_number'] . PHP_EOL;
                            break;
                        }
                    }
                }*/


        //if a collection is empty, pass an empty array
        if ($collections->isEmpty()) {
            $collections = collect([]);
        }
//dd($collections);

        Validator::make($collections->toArray(), $this->rules($collections->toArray()), $this->messages())->validate();


        foreach ($collections as $collection) {
            $majorCategoryId = $this->getMajorCategoryId($collection['major_category']);
            $minorCategoryId = $this->getMinorCategoryId($collection['minor_category'], $majorCategoryId);
            $est_useful_life = $this->getEstUsefulLife($majorCategoryId);
            $formula = $this->createFormula($collection, $est_useful_life);
            $this->createFixedAsset($formula, $collection, $majorCategoryId, $minorCategoryId);
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
        return Formula::create([
            'depreciation_method' => $collection['depreciation_debit_code'] == '-' ? null : (strtoupper($collection['depreciation_method']) == 'STL'
                ? strtoupper($collection['depreciation_method'])
                : ucwords(strtolower($collection['depreciation_method']))),
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

    /**
     * @throws Exception
     */
    private function createFixedAsset($formula, $collection, $majorCategoryId, $minorCategoryId)
    {

        // Check if necessary IDs exist before creating FixedAsset
        if ($majorCategoryId == null || $minorCategoryId == null) {
            throw new Exception('Unable to create FixedAsset due to missing Major/Minor category ID.');
        }
//        $accountingEntry = MinorCategory::where('id', $minorCategoryId)->first()->accounting_entries_id;
        $accountingEntry = AccountingEntries::create([
            'initial_debit' => AccountTitle::where('account_title_code', $collection['initial_debit_code'])->first()->sync_id,
            'initial_credit' => Credit::where('credit_code', $collection['initial_credit_code'])->first()->sync_id,
            'depreciation_debit' => $collection['depreciation_debit_code'] == '-' ? null : AccountTitle::where('account_title_code', $collection['depreciation_debit_code'])->first()->sync_id,
            'depreciation_credit' => Credit::where('credit_code', $collection['depreciation_credit_code'])->first()->sync_id,
        ])->id;

        $formula->fixedAsset()->create([
            'capex_id' => Capex::where('capex', $collection['capex'])->first()->id ?? null,
            'sub_capex_id' => SubCapex::where('sub_capex', $collection['sub_capex'])->first()->id ?? null,
            'vladimir_tag_number' => $this->vladimirTagGeneratorRepository->vladimirTagGenerator(),
            'tag_number' => $collection['tag_number'] ?? '-',
            'tag_number_old' => $collection['tag_number_old'] ?? '-',
            'asset_description' => ucwords(strtolower($collection['description'])),
            'charged_department' => Department::where('department_name', $collection['charged_department'])->first()->id,
            'type_of_request_id' => TypeOfRequest::where('type_of_request_name', ($collection['type_of_request']))->first()->id,
            'asset_specification' => utf8_encode(ucwords(strtolower($collection['additional_description']))),
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
            'uom_id' => UnitOfMeasure::where('uom_name', $collection['unit_of_measure'])->first()->id ?? 6,
            'depreciation_method' => $collection['depreciation_debit_code'] == '-' ? null : (strtoupper($collection['depreciation_method']) == 'STL'
                ? strtoupper($collection['depreciation_method'])
                : ucwords(strtolower($collection['depreciation_method']))),
            'acquisition_date' => $collection['acquisition_date'],
            'acquisition_cost' => $collection['acquisition_cost'],
            'asset_status_id' => AssetStatus::where('asset_status_name', $collection['asset_status'])->first()->id,
            'cycle_count_status_id' => CycleCountStatus::where('cycle_count_status_name', $collection['cycle_count_status'])->first()->id,
            'depreciation_status_id' => DepreciationStatus::where('depreciation_status_name', $collection['depreciation_status'])->first()->id,
            'movement_status_id' => MovementStatus::where('movement_status_name', $collection['movement_status'])->first()->id,
            'is_old_asset' => $collection['tag_number'] != '-' || $collection['tag_number_old'] != '-',
            'care_of' => ucwords(strtolower($collection['care_of'])),
//            'supplier_id' => Supplier::where('supplier_code', $collection['supplier_code'])->first()->id,
            'company_id' => Company::where([
                'company_code' => $collection['company_code'],
                'company_name' => $collection['company']
            ])->first()->id,
            'business_unit_id' => BusinessUnit::where([
                'business_unit_code' => $collection['business_unit_code'],
                'business_unit_name' => $collection['business_unit']
            ])->first()->id,
            'department_id' => Department::where([
                'department_code' => $collection['department_code'],
                'department_name' => $collection['department']
            ])->first()->id,
            'unit_id' => Unit::where([
                'unit_code' => $collection['unit_code'],
                'unit_name' => $collection['unit']
            ])->first()->id,
            'subunit_id' => SubUnit::where([
                'sub_unit_code' => $collection['subunit_code'],
                'sub_unit_name' => $collection['subunit']
            ])->first()->id,
            'location_id' => Location::where([
                'location_code' => $collection['location_code'],
                'location_name' => $collection['location']
            ])->first()->id,
            'account_id' => $accountingEntry,
        ]);
//        dd($formula->fixedAsset());
    }


//Todo: if the id is trashed then what should i do with the id?
    private function rules($collection): array
    {
        $collections = collect($collection);
        $index = array_search('capex', array_keys($collections->toArray()));
        return [
            '*.initial_debit' => ['required'],
            '*.initial_credit' => ['required'],
            '*.depreciation_debit' => ['nullable'],
            '*.depreciation_credit' => ['required'],
            '*.initial_debit_code' => ['required', 'exists:account_titles,account_title_code'],
            '*.initial_credit_code' => ['required', 'exists:credits,credit_code'],
            '*.depreciation_debit_code' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value !== '-') {
                        if (!AccountTitle::where('account_title_code', $value)->exists()) {
                            $fail('The selected depreciation debit code is invalid.');
                        }
                    }
                }
            ],
            '*.depreciation_credit_code' => ['required', 'exists:credits,credit_code'],
//            '*.supplier_code' => ['required', 'exists:suppliers,supplier_code'],
//            '*.supplier' => ['required'],
            '*.capex' => ['nullable', function ($attribute, $value, $fail) use ($collections) {
                if ($value == null || $value == '-') {
                    return true;
                }
                $capex = Capex::where('capex', $value)->first();
                if (!$capex) {
                    $fail('Capex does not exist');
                }
            }],
            '*.sub_capex' => ['nullable', 'regex:/^.+$/', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
                if ($index === false) {
                    return;
                }
                $capexValue = $collections[$index]['capex'];
                $typeOfRequest = $collections[$index]['type_of_request'];
                if (ucwords(strtolower($typeOfRequest)) != 'Capex') {
                    if ($value != '-') {
                        $fail('Capex and Sub Capex should be empty');
                        return true;
                    }
                }

                if ($capexValue != '-') {
                    if ($value == '-') {
                        $fail('Sub Capex is required');
                        return true;
                    }
                } else {
                    if ($value != '-') {
                        $fail('Sub Capex should be empty');
                        return true;
                    }
                }

                $capex = Capex::where('capex', $capexValue)->first();
                if ($capex) {
                    $subCapex = SubCapex::withTrashed()->where('capex_id', $capex->id)->where('sub_capex', $value)->first();
                    if (!$subCapex) {
                        $fail('Sub capex does not exist in the capex');
                    }
                }
            }],
            '*.tag_number' => ['required', 'regex:/^([0-9-]{6,13}|-)$/', function ($attribute, $value, $fail) use ($collections) {
                $duplicate = $collections->where('tag_number', $value)->where('tag_number', '!=', '-')->count();
                if ($duplicate > 1) {
                    $fail('Tag number in row ' . $attribute[0] . ' is not unique');
                }
                $fixed_asset = FixedAsset::withTrashed()->where('tag_number', $value)->where('tag_number', '!=', '-')->first();
                if ($fixed_asset) {
                    $fail('Tag number already exists');
                }
            }],
            '*.tag_number_old' => ['required', 'regex:/^([0-9-]{6,13}|-)$/', function ($attribute, $value, $fail) use ($collections) {
                $duplicate = $collections->where('tag_number_old', $value)->where('tag_number_old', '!=', '-')->count();
                if ($duplicate > 1) {
                    $fail('Tag number old in row ' . $attribute[0] . ' is not unique');
                }
                $fixed_asset = FixedAsset::withTrashed()->where('tag_number_old', $value)->where('tag_number_old', '!=', '-')->first();
                if ($fixed_asset) {
                    $fail('Tag number old already exists');
                }
            }],
            '*.description' => 'required',
            '*.type_of_request' => 'required|exists:type_of_requests,type_of_request_name',
            '*.charged_department' => 'required|exists:departments,department_name',
            '*.additional_description' => 'required',
            '*.accountability' => 'required',
            '*.accountable' => [
                'required_if:*.accountability,Personal Issued',
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
                    if ($index === false) {
                        return;
                    }
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
                }
            ],
            '*.cellphone_number' => 'required',
            '*.brand' => 'required',
            '*.major_category' => [
                'required', 'exists:major_categories,major_category_name'
            ],
            '*.minor_category' => ['required', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
                if ($index === false) {
                    return;
                }
                $major_category = $collections[$index]['major_category'];
                $major_category = MajorCategory::withTrashed()->where('major_category_name', $major_category)->first()->id ?? 0;
                $minor_category = MinorCategory::withTrashed()->where('minor_category_name', $value)
                    ->where('major_category_id', $major_category)->first();
                if (!$minor_category) {
                    $fail('Minor Category does not exist');
                }
            }],
            '*.unit_of_measure' => 'required|exists:unit_of_measures,uom_name',
            '*.voucher' => ['required'],
            '*.voucher_date' => ['required'],
            '*.receipt' => ['required'],
            '*.quantity' => 'required|numeric',
            '*.depreciation_method' => 'required|in:STL,One Time',
            '*.acquisition_date' => ['required', 'string', 'date_format:Y-m-d', 'date', 'before_or_equal:today'],
            '*.acquisition_cost' => ['required', 'numeric', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
                if ($index === false) {
                    return;
                }
                $scrap_value = $collections[$index]['scrap_value'];
                if ($value < $scrap_value) {
                    $fail('Acquisition cost should exceed scrap value.');
                }
                if ($value < 0) {
                    $fail('Acquisition cost must not be negative');
                }
            }],
            '*.scrap_value' => ['required'],
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
                'exists:depreciation_statuses,depreciation_status_name,deleted_at,NULL',
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
                    if ($index === false) {
                        return;
                    }
                    $depreciation = DepreciationStatus::where('depreciation_status_name', $value)->first();
                    if (!$depreciation) {
                        $fail('Invalid depreciation status');
                        return;
                    }
                    if ($depreciation->depreciation_status_name != 'Fully Depreciated' && $depreciation->depreciation_status_name != 'Running Depreciation') {
                        $fail('Invalid depreciation status');
                    }
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
            '*.end_depreciation' => ['required', function ($attribute, $value, $fail) use ($collections) {
                $this->calculationRepository->validationForDate($attribute, $value, $fail, $collections);
            }],
            '*.depreciation_per_year' => ['required'],
            '*.depreciation_per_month' => ['required'],
            '*.remaining_book_value' => ['required', 'numeric', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Remaining book value must not be negative');
                }
            }],
            '*.start_depreciation' => ['required', function ($attribute, $value, $fail) {
                $this->calculationRepository->validationForDate($attribute, $value, $fail);
            }],
            '*.company_code' => ['required', new ValidCompanyCode($collections[$index]['company'] ?? '')],
            '*.business_unit_code' => ['required', new ValidBusinessUnitCode($collections[$index]['company_code'] ?? '', $collections[$index]['business_unit'] ?? '')],
            '*.department_code' => ['required', new ValidDepartmentCode($collections[$index]['business_unit_code'] ?? '', $collections[$index]['department'] ?? '')],
            '*.unit_code' => ['required', new ValidUnitCode($collections[$index]['department_code'] ?? '', $collections[$index]['unit'] ?? '' , $collections[$index]['business_unit_code'] ?? '')],
            '*.subunit_code' => ['required', new ValidSubunitCode($collections[$index]['unit_code'] ?? '', $collections[$index]['subunit'] ?? '')],
            '*.location_code' => ['required', new ValidLocationCode($collections[$index]['subunit_code'] ?? '', $collections[$index]['location'] ?? '')],
        ];
    }

    function messages(): array
    {
        return [
            '*.initial_debit.required' => 'Initial Debit is required',
            '*.initial_credit.required' => 'Initial Credit is required',
            '*.depreciation_debit.required' => 'Depreciation Debit is required',
            '*.depreciation_credit.required' => 'Depreciation Credit is required',
            '*.initial_debit_code.required' => 'Initial Debit Code is required',
            '*.initial_credit_code.required' => 'Initial Credit Code is required',
            '*.depreciation_debit_code.required' => 'Depreciation Debit Code is required',
            '*.depreciation_credit_code.required' => 'Depreciation Credit Code is required',
            '*.capex.exists' => 'Capex does not exist',
            '*.sub_capex.exists' => 'Sub Capex does not exist',
            '*.project_name.exists' => 'Project name does not exist',
            '*.sub_project.exists' => 'Sub project does not exist',
            '*.major_category.exists' => 'Major Category does not exist',
            '*.minor_category.exists' => 'Minor Category does not exist',
            '*.voucher.required' => 'Voucher is required',
            '*.voucher_date.required' => 'Voucher date is required',
            '*.receipt.required' => 'Receipt is required',
            '*.tag_number.required' => 'Tag number is required',
            '*.tag_number.regex' => 'Tag number must be 6 to 13 digits',
            '*.tag_number_old.required' => 'Tag number old is required',
            '*.tag_number_old.regex' => 'Tag number old must be 6 to 13 digits',
            '*.description.required' => 'Description is required',
            '*.type_of_request.required' => 'Type of request is required',
            '*.type_of_request.exists' => 'Type of request does not exist',
            '*.charged_department.required' => 'Charged department is required',
            '*.charged_department.exists' => 'Charged department does not exist',
            '*.additional_description.required' => 'Additional description is required',
            '*.accountability.required' => 'Accountability is required',
            '*.accountable.required_if' => 'Accountable is required',
            '*.cellphone_number.required' => 'Cellphone number is required',
            '*.brand.required' => 'Brand is required',
            '*.major_category.required' => 'Major category is required',
            '*.minor_category.required' => 'Minor category is required',
            '*.quantity.required' => 'Quantity is required',
            '*.depreciation_method.required' => 'Depreciation method is required',
            '*.acquisition_date.required' => 'Acquisition date is required',
            '*.acquisition_date.date_format' => 'Acquisition date must be in Y-m-d format',
            '*.acquisition_date.date' => 'Acquisition date must be a valid date',
            '*.acquisition_date.before_or_equal' => 'Acquisition date must be before or equal to today',
            '*.acquisition_cost.required' => 'Acquisition cost is required',
            '*.acquisition_cost.regex' => 'Acquisition cost must be a number',
            '*.scrap_value.required' => 'Scrap value is required',
            '*.depreciable_basis.required' => 'Depreciable basis is required',
            '*.depreciable_basis.regex' => 'Depreciable basis must be a number',
            '*.accumulated_cost.required' => 'Accumulated cost is required',
            '*.accumulated_cost.regex' => 'Accumulated cost must be a number',
            '*.asset_status.required' => 'Asset status is required',
            '*.asset_status.exists' => 'Asset status does not exist',
            '*.depreciation_status.required' => 'Depreciation status is required',
            '*.depreciation_status.exists' => 'Depreciation status does not exist',
            '*.cycle_count_status.required' => 'Cycle count status is required',
            '*.cycle_count_status.exists' => 'Cycle count status does not exist',
            '*.movement_status.required' => 'Movement status is required',
            '*.movement_status.exists' => 'Movement status does not exist',
            '*.care_of.required' => 'Care of is required',
            '*.end_depreciation.required' => 'End depreciation is required',
            '*.depreciation_per_year.required' => 'Depreciation per year is required',
            '*.depreciation_per_month.required' => 'Depreciation per month is required',
            '*.remaining_book_value.required' => 'Remaining book value is required',
            '*.remaining_book_value.regex' => 'Remaining book value must be a number',
            '*.start_depreciation.required' => 'Start depreciation is required',
            '*.company_code.required' => 'Company code is required',
            '*.company_code.exists' => 'Company code does not exist',
            '*.department_code.required' => 'Department code is required',
            '*.department_code.exists' => 'Department code does not exist',
            '*.location_code.required' => 'Location code is required',
            '*.location_code.exists' => 'Location code does not exist',
            '*.account_code.required' => 'Account code is required',
            '*.account_code.exists' => 'Account code does not exist',
            '*.unit_of_measure.required' => 'Unit of measure is required',
        ];

    }

}
