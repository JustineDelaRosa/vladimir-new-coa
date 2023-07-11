<?php

namespace App\Imports;

use App\Models\AccountTitle;
use App\Models\Capex;
use App\Models\Company;
use App\Models\Division;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\Department;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class MasterlistImport extends DefaultValueBinder implements
    ToCollection,
    WithHeadingRow,
    WithCustomValueBinder,
    WithStartRow,
    WithCalculatedFormulas
{
    use Importable;

    function headingRow(): int
    {
        return 1;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function bindValue(Cell $cell, $value): bool
    {

        if ($cell->getColumn() == 'T') {
            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y-m-d'), DataType::TYPE_STRING);
            return true;
        }
//          elseif ($cell->getColumn() == 'AB') {
//            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y-m'), DataType::TYPE_STRING);
//            return true;
//        } elseif ($cell->getColumn() == 'AG') {
//            $cell->setValueExplicit(Date::excelToDateTimeObject($value)->format('Y'), DataType::TYPE_STRING);
//            return true;
//        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }

    public function collection(Collection $collections)
    {

        Validator::make($collections->toArray(), $this->rules($collections->toArray()), $this->messages())->validate();


        foreach ($collections as $collection) {

                // Check if the major category exists in this division
                $majorCategory = MajorCategory::withTrashed()->where('major_category_name', $collection['major_category'])
                    ->first();
                if ($majorCategory) {
                    $majorCategoryId = $majorCategory->id;

                    // Check if the minor category exists in this major category and division
                    $minorCategory = MinorCategory::withTrashed()->where('minor_category_name', $collection['minor_category'])
                        ->where('major_category_id', $majorCategoryId)
                        ->first();
                    if ($minorCategory) {
                        $minorCategoryId = $minorCategory->id;
                    }
                }


            // Create the Masterlist with the obtained ids
            $fixedAsset = FixedAsset::create([
                'capex_id' => SubCapex::where('sub_capex', $collection['capex'])->first()->id ?? null,
                'project_name' => ucwords(strtolower($collection['project_name'])) ?? '-',
                'vladimir_tag_number' => $this->vladimirTagGenerator(),
                'tag_number' => $collection['tag_number'] ?? '-',
                'tag_number_old' => $collection['tag_number_old'] ?? '-',
                'asset_description' => ucwords(strtolower($collection['description'])),
                'type_of_request_id' => TypeOfRequest::where('type_of_request_name', ($collection['type_of_request']))->first()->id,
                'asset_specification' => ucwords(strtolower($collection['additional_description'])),
                'accountability' => ucwords(strtolower($collection['accountability'])),
                'accountable' => ucwords(strtolower($collection['accountable'])),
                'cellphone_number' => $collection['cellphone_number'],
                'brand' => ucwords(strtolower($collection['brand'])),
                'division_id' => Division::where('division_name', $collection['division'])->first()->id,
                'major_category_id' => $majorCategoryId,
                'minor_category_id' => $minorCategoryId,
                'voucher' => ucwords(strtolower($collection['voucher'])),
                //check for unnecessary spaces and trim them to one space only
                'receipt' => preg_replace('/\s+/', ' ', ucwords(strtolower($collection['receipt']))),
                'quantity' => $collection['quantity'],
                'depreciation_method' => $collection['depreciation_method'] == 'STL' ? strtoupper($collection['depreciation_method']) : ucwords(strtolower($collection['depreciation_method'])),
                'est_useful_life' => $majorCategory->est_useful_life ?? $collection['est_useful_life'],
                'acquisition_date' => $collection['acquisition_date'],
                'acquisition_cost' => $collection['acquisition_cost'],
                'faStatus' => $collection['status'],
                'is_old_asset' => $collection['tag_number'] != '-' || $collection['tag_number_old'] != '-',
                'care_of' => ucwords(strtolower($collection['care_of'])),
                'company_id' => Company::where('company_name', $collection['company'])->first()->id,
                'company_name' => ucwords(strtolower($collection['company'])),
                'department_id' => Department::where('department_name', $collection['department'])->first()->id,
                'department_name' => ucwords(strtolower($collection['department'])),
                'location_id' => Location::where('location_name', $collection['location'])->first()->id,
                'location_name' => ucwords(strtolower($collection['location'])),
                'account_id' => AccountTitle::where('account_title_name', $collection['account_title'])->first()->id,
                'account_title' => ucwords(strtolower($collection['account_title'])),
            ]);

            $fixedAsset->formula()->create(
                [
                    'depreciation_method' => $collection['depreciation_method'] == 'STL' ? strtoupper($collection['depreciation_method']) : ucwords(strtolower($collection['depreciation_method'])),
                    'est_useful_life' => $majorCategory->est_useful_life ?? $collection['est_useful_life'],
                    'acquisition_date' => $collection['acquisition_date'],
                    'acquisition_cost' => $collection['acquisition_cost'],
                    'scrap_value' => $collection['scrap_value'],
                    'original_cost' => $collection['original_cost'],
                    'accumulated_cost' => $collection['accumulated_cost'],
                    'age' => $collection['age'],
                    'end_depreciation' => Carbon::parse(substr_replace($collection['start_depreciation'], '-', 4, 0))->addYears(floor($majorCategory->est_useful_life))->addMonths(floor(($majorCategory->est_useful_life - floor($majorCategory->est_useful_life)) * 12) - 1)->format('Y-m'),
                    'depreciation_per_year' => $collection['depreciation_per_year'],
                    'depreciation_per_month' => $collection['depreciation_per_month'],
                    'remaining_book_value' => $collection['remaining_book_value'],
                    'release_date' => Carbon::parse(substr_replace($collection['start_depreciation'], '-', 4, 0))->subMonth()->format('Y-m'),
                    'start_depreciation' => substr_replace($collection['start_depreciation'], '-', 4, 0),
                ]
            );



//            if the is_status is false, delete the fixed asset and formula
//            if (!$fixedAsset->is_active) {
//                $fixedAsset->delete();
//                $fixedAsset->formula()->delete();
//            }
//            if ($collection['status'] == 'Disposed' || $collection['status'] == 'DISPOSED') {
//                $fixedAsset->delete();
//                $fixedAsset->formula()->delete();
//            }
        }
    }

    function rules($collection): array
    {
        $collections = collect($collection);
        return [
            //if capex is not equal to null or empty, check if it exists in the database
            '*.capex' => ['nullable', function ($attribute, $value, $fail) use ($collections) {
            //check if the value of capex is null or '-'
                if ($value == null || $value == '-') {
                    return true;
                }
                //check if the value of capex exists in the database
                $capex = SubCapex::where('sub_capex', $value)->first();
                if (!$capex) {
                    $fail('Capex does not exist');
                }
            }],
            '*.project_name' => ['nullable', function ($attribute, $value, $fail) use ($collections) {
                //check if the value of project name is null or '-'
                if ($value == null || $value == '-') {
                    return true;
                }
                //check in the capex table if the project name is the same with the capex
                $index = array_search($attribute, array_keys($collections->toArray()));
                $capex = SubCapex::where('sub_capex', $collections[$index]['capex'])->first();
                if ($capex) {
                    $project = $capex->where('sub_project', $value)->first();
                    if (!$project) {
                        $fail('Project name does not exist in the capex');
                    }
                }
            }],
//            '*.vladimir_tag_number' => 'required',
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
            '*.type_of_request' => 'required|in:Asset,CAPEX,Cellular Phone, Major Repair, For Fabrication',
            '*.additional_description' => 'required', //Todo changing asset_specification to Additional Description
            '*.accountability' => 'required',
            '*.accountable' => 'required',
            '*.cellphone_number' => 'required',
            '*.brand' => 'required',
            '*.division' => ['required', function ($attribute, $value, $fail) {
                $division = Division::withTrashed()->where('division_name', $value)->first();
                if (!$division) {
                    $fail('Division does not exists');
                }
            }],
            '*.major_category' => [
                'required', function ($attribute, $value, $fail) use ($collections) {
                    $major_category = MajorCategory::withTrashed()->where('major_category_name', $value)->first();
                    if (!$major_category) {
                        $fail('Major Category does not exists');
                    }
                }
            ],
            '*.minor_category' => ['required', function ($attribute, $value, $fail) use ($collections) {
                $index = array_search($attribute, array_keys($collections->toArray()));
                $status = $collections[$index]['status'];
                $major_category = $collections[$index]['major_category'];
                $major_category = MajorCategory::withTrashed()->where('major_category_name', $major_category)->first()->id ?? 0;
                $minor_category = MinorCategory::withTrashed()->where('minor_category_name', $value)
                    ->where('major_category_id', $major_category)->first();

                if ($minor_category !== null) {
                    if ($status != 'Disposed') {
                        if ($minor_category->trashed()) {
                            $fail('Conflict with minor category and fixed asset status');
                        }
                    }
                } else {
                    $fail('Minor Category does not exist');
                }

            }],
            '*.voucher' => 'required',
            '*.receipt' => 'required',
            '*.quantity' => 'required|numeric',
            '*.depreciation_method' => 'required|in:STL,One Time',
            '*.est_useful_life' => ['required'],
            '*.acquisition_date' => ['required', 'string', 'date_format:Y-m-d', 'date'],
            '*.acquisition_cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Acquisition cost must not be negative');
                }
            }],
            '*.scrap_value' => ['required', ],
            '*.original_cost' => ['required','regex:/^\d+(\.\d{1,2})?$/', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Original cost must not be negative');
                }
            }],
            '*.accumulated_cost' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Accumulated cost must not be negative');
                }
            }],
            '*.status' => 'required|in:Good,For Disposal,Disposed,For Repair,Spare,Sold,Write Off',
            '*.care_of' => 'required',
            '*.age' => 'nullable',
            '*.end_depreciation' => 'required',
            '*.depreciation_per_year' => ['required'],
            '*.depreciation_per_month' => ['required'],
            '*.remaining_book_value' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', function ($attribute, $value, $fail) {
                if ($value < 0) {
                    $fail('Remaining book value must not be negative');
                }
            }],
            '*.start_depreciation' => ['required'],
            '*.company_code' => 'required|exists:companies,company_code',
            '*.company' => 'required|exists:companies,company_name',
            '*.department_code' => 'required|exists:departments,department_code',
            '*.department' => 'required|exists:departments,department_name',
            '*.location_code' => 'required|exists:locations,location_code',
            '*.location' => 'required|exists:locations,location_name',
            '*.account_code' => 'required|exists:account_titles,account_title_code',
            '*.account_title' => 'required|exists:account_titles,account_title_name',
        ];
    }

    function messages(): array
    {
        return [
            '*.capex_id.exists' => 'Capex does not exist',
            '*.project_name.required' => 'Project Name is required',
            '*.vladimir_tag_number.required' => 'Vladimir Tag Number is required',
            '*.tag_number.required' => 'Tag Number is required',
            '*.tag_number_old.required' => 'Tag Number Old is required',
            '*.asset_description.required' => 'Description is required',
            '*.type_of_request.required' => 'Type of Request is required',
            '*.type_of_request.in' => 'Invalid Type of Request',
            '*.additional_description.required' => 'Additional Description is required',
            '*.accountability.required' => 'Accountability is required',
            '*.accountable.required' => 'accountable is required',
            '*.cellphone_number.required' => 'Cellphone Number is required',
            '*.brand.required' => 'Brand is required',
            '*.division.required' => 'Division is required',
            '*.division.exists' => 'Division does not exist',
            '*.major_category.required' => 'Major Category is required',
            '*.minor_category.required' => 'Minor Category is required',
            '*.voucher.required' => 'Voucher is required',
            '*.receipt.required' => 'Receipt is required',
            '*.quantity.required' => 'Quantity is required',
            '*.quantity.numeric' => 'Quantity must be a number',
            '*.depreciation_method.required' => 'Depreciation Method is required',
            '*.depreciation_method.in' => 'The selected depreciation method is invalid.',
            '*.est_useful_life.required' => 'Est Useful Life is required',
            '*.acquisition_date.required' => 'Acquisition Date is required',
            '*.acquisition_cost.required' => 'Acquisition Cost is required',
            '*.scrap_value.required' => 'Scrap Value is required',
            '*.original_cost.required' => 'Original Cost is required',
            '*.accumulated_cost.required' => 'Accumulated Cost is required',
            '*.status.required' => 'Status is required',
            '*.status.in' => 'The selected status is invalid.',
            '*.care_of.required' => 'Care Of is required',
            '*.age.required' => 'Age is required',
            '*.end_depreciation.required' => 'End Depreciation is required',
            '*.depreciation_per_year.required' => 'Depreciation Per Year is required',
            '*.depreciation_per_month.required' => 'Depreciation Per Month is required',
            '*.remaining_book_value.required' => 'Remaining Book Value is required',
            '*.start_depreciation.required' => 'Start Depreciation is required',
            '*.start_depreciation.date_format' => 'Invalid date format',
            '*.company_code.required' => 'Company Code is required',
            '*.company_code.exists' => 'Company Code does not exist',
            '*.company.required' => 'Company is required',
            '*.company.exists' => 'Company does not exist',
            '*.department_code.required' => 'Department Code is required',
            '*.department_code.exists' => 'Department Code does not exist',
            '*.department.required' => 'Department is required',
            '*.department.exists' => 'Department does not exist',
            '*.location_code.required' => 'Location Code is required',
            '*.location_code.exists' => 'Location Code does not exist',
            '*.location.required' => 'Location is required',
            '*.location.exists' => 'Location does not exist',
            '*.account_code.required' => 'Account Code is required',
            '*.account_code.exists' => 'Account Code does not exist',
            '*.account_title.required' => 'Account Title is required',
            '*.account_title.exists' => 'Account Title does not exist',
        ];

    }



//    GENERATING VLADIMIR TAG NUMBER
//    public function vladimirTagGenerator()
//    {
//        $date = date('ymd');
//        static $lastRandom = 0;
//        $generated = [];
//
//        // Generate a new random value
//        do {
//            $random = mt_rand(1, 9) . mt_rand(1000, 9999);
//        } while ($random === $lastRandom);
//
//        $lastRandom = $random;
//        $number = "5$date$random";
//
//        if (strlen($number) !== 12) {
//            return 'Invalid Number';
//        }
//
//        $evenSum = 0;
//        $oddSum = 0;
//
//        for ($i = 1; $i < 12; $i += 2) {
//            $evenSum += (int)$number[$i];
//        }
//        $evenSum *= 3;
//
//        for ($i = 0; $i < 12; $i += 2) {
//            $oddSum += (int)$number[$i];
//        }
//
//        $totalSum = $evenSum + $oddSum;
//        $remainder = $totalSum % 10;
//        $checkDigit = ($remainder === 0) ? 0 : 10 - $remainder;
//
//        $ean13Result = $number . $checkDigit;
//
//        // Check if the generated number is a duplicate or already exists in the database
//        while (in_array($ean13Result, $generated) || FixedAsset::where('vladimir_tag_number', $ean13Result)->exists()) {
//            // Regenerate the number
//            $ean13Result = $this->vladimirTagGenerator();
//        }
//        return $ean13Result;
//    }

    public function vladimirTagGenerator()
    {
        $generatedEan13Result = $this->generateEan13();
        // Check if the generated number is a duplicate or already exists in the database
        while ($this->checkDuplicateEan13($generatedEan13Result)) {
            $generatedEan13Result = $this->generateEan13();
        }

        return $generatedEan13Result;
    }

    public function generateEan13(): string
    {
        $date = date('ymd');
        static $lastRandom = 0;
        do {
            $random = mt_rand(1, 9) . mt_rand(1000, 9999);
        } while ($random === $lastRandom);
        $lastRandom = $random;

        $number = "5$date$random";

        if (strlen($number) !== 12) {
            return 'Invalid Number';
        }

        //Calculate checkDigit
        $checkDigit = $this->calculateCheckDigit($number);

        $ean13Result = $number . $checkDigit;

        return $ean13Result;
    }

    public function calculateCheckDigit(string $number): int
    {
        $evenSum = $this->calculateEvenSum($number);
        $oddSum = $this->calculateOddSum($number);

        $totalSum = $evenSum + $oddSum;
        $remainder = $totalSum % 10;
        $checkDigit = ($remainder === 0) ? 0 : 10 - $remainder;

        return $checkDigit;
    }

    public function calculateEvenSum(string $number): int
    {
        $evenSum = 0;
        for ($i = 1; $i < 12; $i += 2) {
            $evenSum += (int)$number[$i];
        }
        return $evenSum * 3;
    }

    public function calculateOddSum(string $number): int
    {
        $oddSum = 0;
        for ($i = 0; $i < 12; $i += 2) {
            $oddSum += (int)$number[$i];
        }
        return $oddSum;
    }

    public function checkDuplicateEan13(string $ean13Result): bool
    {
        $generated = [];
        return in_array($ean13Result, $generated) || FixedAsset::where('vladimir_tag_number', $ean13Result)->exists();
    }

}
