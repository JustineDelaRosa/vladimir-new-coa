<?php

namespace App\Imports;

use App\Models\AccountTitle;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\OneCharging;
use App\Models\SubUnit;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class OneChargingUpdateImport implements ToCollection, WithHeadingRow, WithValidation, WithStartRow
{
    use apiResponse;

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $collection)
    {
        Validator::make($collection->toArray(), $this->rules(), $this->messages())->validate();
        $collection->chunk(150)->each(function ($rows) {
            foreach ($rows as $index => $row) {

                $oneCharging = OneCharging::where([
                    'code' => $row['one_charging_code'],
                    'name' => $row['one_charging']
                ])->first();
                $company = Company::where([
                    'company_code' => $row['company_code'],
                    'company_name' => $row['company']
                ])->first();
                if(!$company) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            sprintf('%d.company', $index) => [sprintf(
                                'Please check the company code "%s" and company name "%s".',
                                $row['company_code'],
                                $row['company']
                            )]
                        ]
                    ], 422));
                }

                $businessUnit = BusinessUnit::where([
                    'business_unit_code' => $row['business_unit_code'],
                    'business_unit_name' => $row['business_unit'],
                ])->first();
                if (!$businessUnit) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            sprintf('%d.business_unit', $index) => [sprintf(
                                'Please check the business unit code "%s" and business unit name "%s".',
                                $row['business_unit_code'],
                                $row['business_unit']
                            )]
                        ]
                    ], 422));
                }
                $department = Department::where([
                    'department_code' => $row['department_code'],
                    'department_name' => $row['department'],
                ])->first();
                if (!$department) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            sprintf('%d.department', $index) => [sprintf(
                                'Please check the department code "%s" and department name "%s".' . $businessUnit->sync_id,
                                $row['department_code'],
                                $row['department']
                            )]
                        ]
                    ], 422));
                }
                $unit = Unit::where([
                    'unit_code' => $row['unit_code'],
                    'unit_name' => $row['unit'],
                ])->first();
                if (!$unit) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            sprintf('%d.unit', $index) => [sprintf(
                                'Please check the unit code "%s" and unit name "%s".' . $department,
                                $row['unit_code'],
                                $row['unit']
                            )]
                        ]
                    ], 422));
                }
                $subUnit = SubUnit::where([
                    'sub_unit_code' => $row['sub_unit_code'],
                    'sub_unit_name' => $row['sub_unit'],
                ])->first();
                if (!$subUnit) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            sprintf('%d.sub_unit', $index) => [sprintf(
                                'Please check the sub unit code "%s" and sub unit name "%s".',
                                $row['sub_unit_code'],
                                $row['sub_unit']
                            )]
                        ]
                    ], 422));
                }
                $location = Location::where([
                    'location_code' => $row['location_code'],
                    'location_name' => $row['location'],
                ])->first();


                $fixedAsset = FixedAsset::where('vladimir_tag_number', $row['vladimir_tag_number'])->first();
//                $additionalCost = AdditionalCost::where(['fixed_asset_id' => $fixedAsset->id, 'add_cost_sequence' => $row['additional_cost_sequence']])->first();

                if ($fixedAsset && ($row['additional_cost_sequence'] === null || $row['additional_cost_sequence'] === "-")) {


                    $fixedAsset->update([
                        'company_id' => $company->id,
                        'business_unit_id' => $businessUnit->id,
                        'charged_department' => $department->id,
                        'department_id' => $department->id,
                        'unit_id' => $unit->id,
                        'subunit_id' => $subUnit->id,
                        'location_id' => $location->id,
                        'one_charging_id' => $oneCharging->id
                    ]);

                    //if the fixed asset has an entries to depreciation history table, update the company, business unit, department, unit, subunit, location and one charging
                    $fixedAsset->depreciationHistory()->update([
                        'company_id' => $company->id,
                        'business_unit_id' => $businessUnit->id,
                        'department_id' => $department->id,
                        'unit_id' => $unit->id,
                        'subunit_id' => $subUnit->id,
                        'location_id' => $location->id,
                        'one_charging_id' => $oneCharging->id
                    ]);

                }

                if ($row['additional_cost_sequence'] !== null || $row['additional_cost_sequence'] !== "-") {
//                dd($fixedAsset->additionalCost()->get()->toArray());
                    $fixedAsset->additionalCost()->update([
                        'company_id' => $company->id,
                        'business_unit_id' => $businessUnit->id,
                        'department_id' => $department->id,
                        'unit_id' => $unit->id,
                        'subunit_id' => $subUnit->id,
                        'location_id' => $location->id,
                        'one_charging_id' => $oneCharging->id
                    ]);
                }
            }
        });
    }


    public function rules(): array
    {
        return [
            '*.vladimir_tag_number' => ['required', 'exists:fixed_assets,vladimir_tag_number'],
            '*.additional_cost_sequence' => ['nullable'],
//            '*.initial_debit' => ['required'],
//            '*.initial_credit' => ['required'],
//            '*.depreciation_debit' => ['required'],
//            '*.depreciation_credit' => ['required'],
//            '*.initial_debit_code' => ['required', 'exists:account_titles,account_title_code'],
//            '*.initial_credit_code' => ['required', 'exists:credits,credit_code'],
//            '*.depreciation_debit_code' => ['required', 'exists:account_titles,account_title_code'],
//            '*.depreciation_credit_code' => ['required', 'exists:credits,credit_code'],
            '*.company' => ['required'],
            '*.business_unit' => ['required'],
            '*.department' => ['required'],
            '*.unit' => ['required'],
            '*.sub_unit' => ['required'],
            '*.location' => ['required'],
            '*.one_charging_code' => [
                'required',
                Rule::exists('one_chargings', 'code')->whereNull('deleted_at'),
            ],
            '*.company_code' => ['required', 'exists:companies,company_code'],
            '*.business_unit_code' => ['required', 'exists:business_units,business_unit_code'],
            '*.department_code' => ['required', 'exists:departments,department_code'],
            '*.unit_code' => ['required', 'exists:units,unit_code'],
            '*.sub_unit_code' => ['required', 'exists:sub_units,sub_unit_code'],
            '*.location_code' => ['required', 'exists:locations,location_code'],
//            '*.uom' => ['required'],
//            '*.uom_code' => ['required', 'exists:unit_of_measures,uom_code'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.vladimir_tag_number.required' => 'The vladimir tag number is required.',
            '*.vladimir_tag_number.exists' => 'The vladimir tag number does not exist.',
            '*.additional_cost_sequence.required' => 'The additional cost sequence is required.',
            '*.company.required' => 'The company is required.',
            '*.business_unit.required' => 'The business unit is required.',
            '*.department.required' => 'The department is required.',
            '*.unit.required' => 'The unit is required.',
            '*.sub_unit.required' => 'The sub unit is required.',
            '*.location.required' => 'The location is required.',
            '*.one_charging_code.required' => 'The one charging code is required.',
            '*.one_charging_code.exists' => 'The one charging code does not exist or has been deleted.',
            '*.company_code.required' => 'The company code is required.',
            '*.business_unit_code.required' => 'The business unit code is required.',
            '*.department_code.required' => 'The department code is required.',
            '*.unit_code.required' => 'The unit code is required.',
            '*.sub_unit_code.required' => 'The sub unit code is required.',
            '*.location_code.required' => 'The location code is required.',
        ];
    }
}
