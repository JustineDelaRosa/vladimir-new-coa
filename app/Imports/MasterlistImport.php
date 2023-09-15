<?php

namespace App\Imports;

use App\Http\Controllers\Masterlist\FixedAssetController;
use App\Models\AccountTitle;
use App\Models\Capex;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Location;
use App\Models\Department;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\Status\AssetStatus;
use App\Models\Status\CycleCountStatus;
use App\Models\Status\DepreciationStatus;
use App\Models\Status\MovementStatus;
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use App\Repositories\CalculationRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use function PHPUnit\Framework\isEmpty;

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

//        $client = new Client();
//        $token = '9|u27KMjj3ogv0hUR8MMskyNmhDJ9Q8IwUJRg8KAZ4';
//        $response = $client->request('GET', 'http://rdfsedar.com/api/data/employees', [
//            'headers' => [
//                'Authorization' => 'Bearer ' . $token,
//                'Accept' => 'application/json',
//            ],
//        ]);
//
//// Get the body content from the response
//        $body = $response->getBody()->getContents();
//
//// Decode the JSON response into an associative array
//        $data = json_decode($body, true);
//        $nameToCheck = [
//            'Perona, jerome',
//            'Dela Rosa, Justine',
//            'Nucum, Caren'
//        ];
//
//        if (!empty($data['data']) && is_array($data['data'])) {
//            foreach ($data['data'] as $employee) {
//                if (!empty($employee['general_info']) && in_array($employee['general_info']['full_name'], $nameToCheck)) {
//                    echo $employee['general_info']['full_id_number'] . PHP_EOL;
//                    break;
//                }
//            }
//        }
        //if a collection is empty, pass an empty array
        if ($collections->isEmpty()) {
            $collections = collect([]);
        }

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

    /**
     * @throws Exception
     */
    private function createFixedAsset($formula, $collection, $majorCategoryId, $minorCategoryId)
    {
        // Check if necessary IDs exist before creating FixedAsset
        if ($majorCategoryId == null || $minorCategoryId == null) {
            throw new Exception('Unable to create FixedAsset due to missing Major/Minor category ID.');
        }


        $formula->fixedAsset()->create([
            'capex_id' => Capex::where('capex', $collection['capex'])->first()->id ?? null,
            'sub_capex_id' => SubCapex::where('sub_capex', $collection['sub_capex'])->first()->id ?? null,
            'vladimir_tag_number' => $this->vladimirTagGeneratorRepository->vladimirTagGenerator(),
            'tag_number' => $collection['tag_number'] ?? '-',
            'tag_number_old' => $collection['tag_number_old'] ?? '-',
            'asset_description' => ucwords(strtolower($collection['description'])),
            'charged_department' => Department::where('department_name', $collection['charged_department'])->first()->id,
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
            'depreciation_method' => $collection['depreciation_method'] == 'STL' ? strtoupper($collection['depreciation_method']) : ucwords(strtolower($collection['depreciation_method'])),
            'acquisition_date' => $collection['acquisition_date'],
            'acquisition_cost' => $collection['acquisition_cost'],
            'asset_status_id' => AssetStatus::where('asset_status_name', $collection['asset_status'])->first()->id,
            'cycle_count_status_id' => CycleCountStatus::where('cycle_count_status_name', $collection['cycle_count_status'])->first()->id,
            'depreciation_status_id' => DepreciationStatus::where('depreciation_status_name', $collection['depreciation_status'])->first()->id,
            'movement_status_id' => MovementStatus::where('movement_status_name', $collection['movement_status'])->first()->id,
            'is_old_asset' => $collection['tag_number'] != '-' || $collection['tag_number_old'] != '-',
            'care_of' => ucwords(strtolower($collection['care_of'])),
            'company_id' => Company::where('company_code', $collection['company_code'])->first()->id,
            'department_id' => Department::where('department_code', $collection['department_code'])->first()->id,
            'location_id' => Location::where('location_code', $collection['location_code'])->first()->id,
            'account_id' => AccountTitle::where('account_title_code', $collection['account_code'])->first()->id,
        ]);
//        dd($formula->fixedAsset());
    }


//Todo: if the id is trashed then what should i do with the id?
    function rules($collection): array
    {
        $collections = collect($collection);
        return [
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
                $capexValue = $collections[$index]['capex'];
                $typeOfRequest = $collections[$index]['type_of_request'];
                if (ucwords(strtolower($typeOfRequest)) != 'Capex') {
                    if ($value != '-') {
                        $fail('Capex and Sub Capex should be empty');
                        return true;
                    }
                }

                //todo:check for other way to check if the value is null or '-'
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
//            '*.project_name' => ['nullable', function ($attribute, $value, $fail) use ($collections) {
//                //check if the value of project name is null or '-'
//                if ($value == null || $value == '-') {
//                    return true;
//                }
//                //check in the capex table if the project name is the same with the capex
//                $index = array_search($attribute, array_keys($collections->toArray()));
//                $capex = Capex::where('capex', $collections[$index]['capex'])->first();
//                if ($capex) {
//                    $project = $capex->where('project_name', $value)->first();
//                    if (!$project) {
//                        $fail('Project name does not exist in the capex');
//                    }
//                }
//            }],
//            '*.sub_project' => ['nullable', function ($attribute, $value, $fail) use ($collections) {
//                if ($value == '' || $value == '-') {
//                    return true;
//                }
//                $index = array_search($attribute, array_keys($collections->toArray()));
//                $subCapexValue = $collections[$index]['sub_capex'];
//                if($subCapexValue != '' && $subCapexValue != '-'){
//                    if ($value == '' || $value == '-') {
//                        $fail('Sub Project is required');
//                        return true;
//                    }
//                }
//                //check in the sub capex table if the subproject is the same with the capex
//                $subCapex = SubCapex::where('sub_capex', $subCapexValue)->first();
//                if ($subCapex) {
//                    $subProject = $subCapex->where('sub_project', $value)->first();
//                    if (!$subProject) {
//                        $fail('Sub project does not exist in the sub capex');
//                    }
//                }
//            }],
            '*.tag_number' => ['required', 'regex:/^([0-9-]{6,13}|-)$/', function ($attribute, $value, $fail) use ($collections) {
                $duplicate = $collections->where('tag_number', $value)->where('tag_number', '!=', '-')->count();
                if ($duplicate > 1) {
                    $fail('Tag number in row ' . $attribute[0] . ' is not unique');
                }
                //check in a database
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
                //check in a database
                $fixed_asset = FixedAsset::withTrashed()->where('tag_number_old', $value)->where('tag_number_old', '!=', '-')->first();
                if ($fixed_asset) {
                    $fail('Tag number old already exists');
                }
            }],
            '*.description' => 'required', //todo: changing asset_description to description
            '*.type_of_request' => 'required|exists:type_of_requests,type_of_request_name',
            '*.charged_department' => 'required|exists:departments,department_name',
            '*.additional_description' => 'required', //Todo changing asset_specification to Additional Description
            '*.accountability' => 'required',
            //required if accountability is personally issued and if accountability is common, it should be empty
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
                }],
            '*.cellphone_number' => 'required',
            '*.brand' => 'required',
            '*.major_category' => [
                'required', 'exists:major_categories,major_category_name'
//                function ($attribute, $value, $fail) use ($collections) {
//                    $major_category = MajorCategory::withTrashed()->where('major_category_name', $value)->first();
//                    if (!$major_category) {
//                        $fail('Major Category does not exist');
//                    }
//                }
            ],
            '*.minor_category' => ['required', function ($attribute, $value, $fail) use ($collections) {
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

            }],

            '*.voucher' => ['required', function ($attribute, $value, $fail) {
//                if ($value == '-') {
////                    $fail('Voucher is required');
//                }
//                $voucher = FixedAsset::where('voucher', $value)->first();
//                //check the created_at if it is the same date with the uploaded date of the voucher if it is the same then it will pass the validation
//                if ($voucher) {
//                    $uploaded_date = Carbon::parse($voucher->created_at)->format('Y-m-d');
//                    $current_date = Carbon::now()->format('Y-m-d');
//                    if ($uploaded_date != $current_date) {
//                        $fail('Voucher previously uploaded.');
//                    }
//                }

            }],
            '*.voucher_date' => [
                'required',
                function ($attribute, $value, $fail) use ($collections) {
                    $index = array_search($attribute, array_keys($collections->toArray()));
                    $voucher = $collections[$index]['voucher'];
                    if ($voucher == '-') {
                        if ($value != '-') {
                            $fail('Voucher date should be empty');
                            return;
                        }
                        return;
                    }
                    $this->calculationRepository->validationForDate($attribute, $value, $fail, $collections);
                }
            ],
            '*.receipt' => ['required', function ($attribute, $value, $fail) {
//                if ($value == '-') {
//                    $fail('Receipt is required');
//                }
            }],
            '*.quantity' => 'required|numeric',
            '*.depreciation_method' => 'required|in:STL,One Time',
            '*.acquisition_date' => ['required', 'string', 'date_format:Y-m-d', 'date', 'before_or_equal:today'],
            '*.acquisition_cost' => ['required', 'numeric', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
                $scrap_value = $collections[$index]['scrap_value'];

                if ($value < $scrap_value) {
                    $fail('Acquisition cost should exceed scrap value.');
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
//            ''regex:/^\d+(\.\d{1,2})?$/''
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
            '*.company_code' => ['required', 'exists:companies,company_code', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
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
                $index = array_search($attribute, array_keys($collections->toArray()));
                $department_name = $collections[$index]['department'];
                $department = Department::query()
                    ->where('department_code', $value)
                    ->where('department_name', $department_name)
                    ->where('is_active', '!=', 0)
                    ->first();
                if (!$department) {
                    $fail('Invalid department');
                }
                $company_code = $collections[$index]['company_code'];
                $company_sync_id = Company::where('company_code', $company_code)->first()->sync_id ?? 0;
                $departmentCompCheck = Department::where('department_code', $value)
                    ->where('company_sync_id', $company_sync_id)
                    ->first();
                if (!$departmentCompCheck) {
                    $fail('Invalid department and company combination');
                }
            }],
            '*.location_code' => ['required', 'exists:locations,location_code', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
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
                    $fail('Invalid location, company and department combination');
                }

            }],
            '*.account_code' => ['required', 'exists:account_titles,account_title_code', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
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

    function messages(): array
    {
        return [
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
        ];

    }

}
