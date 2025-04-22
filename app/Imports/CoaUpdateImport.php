<?php

namespace App\Imports;

use App\Models\AccountTitle;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Location;
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

class CoaUpdateImport implements ToCollection, WithHeadingRow, WithValidation, WithStartRow
{
    use apiResponse;

    public function startRow(): int
    {
        return 2;
    }


    public function collection(Collection $collection)
    {
//        dd($collection);

//        dd($collection->toArray());
        Validator::make($collection->toArray(), $this->rules(), $this->messages())->validate();
        $collection->chunk(150)->each(function ($rows) {
            foreach ($rows as $index => $row) {
                $company = Company::where([
                    'company_code' => $row['company_code'],
                    'company_name' => $row['company']
                ])->first();

                $businessUnit = BusinessUnit::where([
                    'business_unit_code' => $row['business_unit_code'],
                    'business_unit_name' => $row['business_unit'],
                    'company_sync_id' => $company->sync_id
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
                /*$businessUnit->departments()->where([
                    'department_code' => $row['department_code'],
                    'department_name' => $row['department'],
                    'business_unit_sync_id' => $businessUnit->sync_id
                ])->first();*/
                $department = Department::where([
                    'department_code' => $row['department_code'],
                    'department_name' => $row['department'],
                    'business_unit_sync_id' => $businessUnit->sync_id
                ])->first();
                if (!$department) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            sprintf('%d.department', $index) => [sprintf(
                                'Please check the department code "%s" and department name "%s".'. $businessUnit->sync_id,
                                $row['department_code'],
                                $row['department']
                            )]
                        ]
                    ], 422));
                }
                $unit = Unit::where([
                    'unit_code' => $row['unit_code'],
                    'unit_name' => $row['unit'],
                    'department_sync_id' => $department->sync_id
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
                    'unit_sync_id' => $unit->sync_id
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
                $uom = UnitOfMeasure::where([
                    'uom_code' => $row['uom_code'],
                    'uom_name' => $row['uom']
                ])->first();


                //ACCOUNTING ENTRIES
                $initialDebit = AccountTitle::where([
                    'account_title_code' => $row['initial_debit_code'],
                    'account_title_name' => $row['initial_debit']
                ])->first();
                $initialCredit = Credit::where([
                    'credit_code' => $row['initial_credit_code'],
                    'credit_name' => $row['initial_credit']
                ])->first();
                $depreciationDebit = AccountTitle::where([
                    'account_title_code' => $row['depreciation_debit_code'],
                    'account_title_name' => $row['depreciation_debit']
                ])->first();
                $depreciationCredit = credit::where([
                    'credit_code' => $row['depreciation_credit_code'],
                    'credit_name' => $row['depreciation_credit']
                ])->first();


                $fixedAsset = FixedAsset::where('vladimir_tag_number', $row['vladimir_tag_number'])->first();
//                $additionalCost = AdditionalCost::where(['fixed_asset_id' => $fixedAsset->id, 'add_cost_sequence' => $row['additional_cost_sequence']])->first();

                if ($fixedAsset && ($row['additional_cost_sequence'] === null || $row['additional_cost_sequence'] === "-")) {
                    /*                    $fixedAsset->update([
                                            'company_id' => $company->id,
                                            'business_unit_id' => $businessUnit->id,
                                            'department_id' => $department->id,
                                            'unit_id' => $unit->id,
                                            'subunit_id' => $subUnit->id,
                                            'location_id' => $location->id,
                                            'uom_id' => $uom->id,
                                        ]);

                                        $fixedAsset->accountingEntries()*/

//                    $fixedAsset->accountingEntries()->delete();
                    $accountingEntry = $fixedAsset->accountingEntries()->updateOrCreate(
                        [
                            'initial_debit' => $initialDebit->sync_id,
                            'initial_credit' => $initialCredit->sync_id,
                            'depreciation_debit' => $depreciationDebit->sync_id,
                            'depreciation_credit' => $depreciationCredit->sync_id,
                        ],
                        [
                            'initial_debit' => $initialDebit->sync_id,
                            'initial_credit' => $initialCredit->sync_id,
                            'depreciation_debit' => $depreciationDebit->sync_id,
                            'depreciation_credit' => $depreciationCredit->sync_id,
                        ]
                    );

                    $fixedAsset->update([
                        'company_id' => $company->id,
                        'business_unit_id' => $businessUnit->id,
                        'charged_department' => $department->id,
                        'department_id' => $department->id,
                        'unit_id' => $unit->id,
                        'subunit_id' => $subUnit->id,
                        'location_id' => $location->id,
                        'uom_id' => $uom->id,
                        'account_id' => $accountingEntry->id
                    ]);

                    $fixedAsset->depreciationHistory()->update([
                        'account_id' => $accountingEntry->id
                    ]);
                }

                if ($row['additional_cost_sequence'] != null || $row['additional_cost_sequence'] != "-") {
//                dd($fixedAsset->additionalCost()->get()->toArray());
                    $fixedAsset->additionalCost()->update([
                        'company_id' => $company->id,
                        'business_unit_id' => $businessUnit->id,
                        'department_id' => $department->id,
                        'unit_id' => $unit->id,
                        'subunit_id' => $subUnit->id,
                        'location_id' => $location->id,
                        'uom_id' => $uom->id,
                        'account_id' => $fixedAsset->account_id
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
            '*.initial_debit' => ['required'],
            '*.initial_credit' => ['required'],
            '*.depreciation_debit' => ['required'],
            '*.depreciation_credit' => ['required'],
            '*.initial_debit_code' => ['required', 'exists:account_titles,account_title_code'],
            '*.initial_credit_code' => ['required', 'exists:credits,credit_code'],
            '*.depreciation_debit_code' => ['required', 'exists:account_titles,account_title_code'],
            '*.depreciation_credit_code' => ['required', 'exists:credits,credit_code'],
            '*.company' => ['required'],
            '*.business_unit' => ['required'],
            '*.department' => ['required'],
            '*.unit' => ['required'],
            '*.sub_unit' => ['required'],
            '*.location' => ['required'],
            '*.company_code' => ['required', 'exists:companies,company_code'],
            '*.business_unit_code' => ['required', 'exists:business_units,business_unit_code'],
            '*.department_code' => ['required', 'exists:departments,department_code'],
            '*.unit_code' => ['required', 'exists:units,unit_code'],
            '*.sub_unit_code' => ['required', 'exists:sub_units,sub_unit_code'],
            '*.location_code' => ['required', 'exists:locations,location_code'],
            '*.uom' => ['required'],
            '*.uom_code' => ['required', 'exists:unit_of_measures,uom_code'],
        ];
    }

    private function messages()
    {
        return [
            '*.vladimir_tag_number.exists' => 'Vladimir Tag Number does not exist in the database.',
            '*.vladimir_tag_number.required' => 'Vladimir Tag Number is required.',
            '*.initial_debit.required' => 'Initial Debit is required.',
            '*.initial_credit.required' => 'Initial Credit is required.',
            '*.depreciation_debit.required' => 'Depreciation Debit is required.',
            '*.depreciation_credit.required' => 'Depreciation Credit is required.',
            '*.initial_debit_code.required' => 'Initial Debit Code is required.',
            '*.initial_credit_code.required' => 'Initial Credit Code is required.',
            '*.depreciation_debit_code.required' => 'Depreciation Debit Code is required.',
            '*.depreciation_credit_code.required' => 'Depreciation Credit Code is required.',
            '*.initial_debit_code.exists' => 'Initial Debit Code does not exist in the database.',
            '*.initial_credit_code.exists' => 'Initial Credit Code does not exist in the database.',
            '*.depreciation_debit_code.exists' => 'Depreciation Debit Code does not exist in the database.',
            '*.depreciation_credit_code.exists' => 'Depreciation Credit Code does not exist in the database.',
            '*.company.required' => 'Company is required.',
            '*.business_unit.required' => 'Business Unit is required.',
            '*.department.required' => 'Department is required.',
            '*.unit.required' => 'Unit is required.',
            '*.sub_unit.required' => 'Sub Unit is required.',
            '*.location.required' => 'Location is required.',
            '*.company_code.required' => 'Company Code is required.',
            '*.business_unit_code.required' => 'Business Unit Code is required.',
            '*.department_code.required' => 'Department Code is required.',
            '*.unit_code.required' => 'Unit Code is required.',
            '*.sub_unit_code.required' => 'Sub Unit Code is required.',
            '*.location_code.required' => 'Location Code is required.',
            '*.uom.required' => 'Unit of Measure is required.',
            '*.uom_code.required' => 'Unit of Measure Code is required.',
            '*.uom_code.exists' => 'Unit of Measure Code does not exist in the database.',
            '*.company_code.exists' => 'Company Code does not exist in the database.',
            '*.business_unit_code.exists' => 'Business Unit Code does not exist in the database.',
            '*.department_code.exists' => 'Department Code does not exist in the database.',
            '*.unit_code.exists' => 'Unit Code does not exist in the database.',
            '*.sub_unit_code.exists' => 'Sub Unit Code does not exist in the database.',
            '*.location_code.exists' => 'Location Code does not exist in the database.',
        ];
    }

}
