<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingEntries\CreateAccountingEntriesRequest;
use App\Http\Requests\FixedAsset\FixedAssetRequest;
use App\Http\Requests\FixedAsset\FixedAssetUpdateRequest;
use App\Models\AdditionalCost;
use App\Models\AssetMovementHistory;
use App\Models\AssetRequest;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\DepreciationHistory;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\PoBatch;
use App\Models\PullOut;
use App\Models\Status\DepreciationStatus;
use App\Models\SubCapex;
use App\Models\Transfer;
use App\Models\TypeOfRequest;
use App\Models\User;
use App\Models\YmirPRTransaction;
use App\Repositories\CalculationRepository;
use App\Repositories\FixedAssetRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FixedAssetController extends Controller
{
    use ApiResponse;

    protected $fixedAssetRepository, $vladimirTagGeneratorRepository, $calculationRepository;

    public function __construct()
    {
        $this->fixedAssetRepository = new FixedAssetRepository();
        $this->vladimirTagGeneratorRepository = new VladimirTagGeneratorRepository();
        $this->calculationRepository = new CalculationRepository();
    }


    public function index(Request $request)
    {

//                AssetMovementHistory::
//        return FixedAsset::with('assetSmallTools')->wherehas('assetSmallTools')->get();

//        return $smallTollItems = SmallTools::where('id', 1)->first()->item;
//       return  auth('sanctum')->user()->department_id;
        $movement = $request->get('movement');
        $subUnitId = $request->get('sub_unit_id', null);
        $addCost = $request->get('add_cost', false);
        $ymir = $request->get('ymir', false);
        $smallTools = $request->get('small_tools', false);
        $replacement = $request->get('replacement', false);
//        $data = Cache::get('fixed_assets_data');
//        return FixedAsset::get();
//        return DB::table('fixed_assets')->get();
        return $this->fixedAssetRepository->faIndex($ymir, $addCost, $movement, $subUnitId, $smallTools);


//        if ($data) {
////            return $this->fixedAssetRepository->faIndex();
//            return Crypt::decrypt($data);
//        } else {
////            return 'none';
//            return $this->fixedAssetRepository->faIndex();
//        }
    }


    public function store(FixedAssetRequest $request)
    {
        DB::beginTransaction();

        try {
            $vladimirTagNumber = $this->vladimirTagGeneratorRepository->vladimirTagGenerator();
            if (!is_numeric($vladimirTagNumber) || strlen($vladimirTagNumber) != 13) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => 'Wrong vladimir tag number format. Please try again.'
                ], 422);
            }

            // Minor Category check
            $majorCategory = MajorCategory::withTrashed()->where('id', $request->major_category_id)->first();
            $minorCategoryCheck = MinorCategory::withTrashed()->where('id', $request->minor_category_id)
                ->where('major_category_id', $majorCategory->id)->exists();

            if (!$minorCategoryCheck) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'minor_category' => [
                            'The minor category does not match the major category.'
                        ]
                    ]
                ], 422);
            }

            $businessUnitQuery = BusinessUnit::where('id', $request->business_unit_id)->first();
            $fixedAsset = $this->fixedAssetRepository->storeFixedAsset($request->all(), $vladimirTagNumber, $businessUnitQuery);

            if ($fixedAsset === "Not yet fully depreciated") {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'end_depreciation' => [
                            $fixedAsset
                        ]
                    ]
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => 'Fixed Asset created successfully.',
                'data' => $fixedAsset
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while creating the fixed asset.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, int $id)
    {

        $fixed_asset = FixedAsset::withTrashed()->with([
            'formula' => function ($query) {
                $query->withTrashed();
            },
            'additionalCost' => function ($query) {
                $query->withTrashed();
            },
        ])->where('id', $id)->first();


        //        return $fixed_asset->majorCategory->major_category_name;
        if (!$fixed_asset) {
            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
        }

        return response()->json([
            'message' => 'Fixed Asset retrieved successfully.',
            'data' => $this->fixedAssetRepository->transformSingleFixedAsset($fixed_asset)
        ], 200);
    }

    public function nextToDepreciate()
    {
        $fixed_asset = FixedAsset::whereNull('depreciation_method')->where('is_released', 1)->first();
        if (!$fixed_asset) {
            return $this->responseNotFound('Fixed Asset Route Not Found');
        }

        return $this->fixedAssetRepository->transformSingleFixedAsset($fixed_asset);

//        'depreciation_method' => null, 'is_released' => 1
    }


    //TODO: Ask on what should and should not be updated on the fixed asset
    public function update(FixedAssetUpdateRequest $request, int $id)
    {
        $request->validated();
        //minor Category check
        $majorCategory = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->first()->id;
        $minorCategoryCheck = MinorCategory::withTrashed()->where('id', $request->minor_category_id)
            ->where('major_category_id', $majorCategory)->exists();

        $depreciationHistoryCheck = DepreciationHistory::where('fixed_asset_id', $id)->exists();

        /*        if($depreciationHistoryCheck){
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'depreciation_history' => [
                                'This Asset has already been depreciated. You cannot update this asset.'
                            ]
                        ]
                    ], 422);
                }*/

        if (!$minorCategoryCheck) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'minor_category' => [
                            'The minor category does not match the major category.'
                        ]
                    ]
                ],
                422
            );
        }
        //if no changes in all fields
//        if(FixedAsset::where('id', $id)->first()) {
//            return response()->json([
//                'message' => 'No changes made.',
//                'data' => $request->all()
//            ], 200);
//        }
        $departmentQuery = Department::where('id', $request->department_id)->first();
        $businessUnitQuery = BusinessUnit::where('id', $request->business_unit_id)->first();
        $fixedAsset = FixedAsset::where('id', $id)->first();
        if ($fixedAsset) {
            $fixed_asset = $this->fixedAssetRepository->updateFixedAsset($request->all(), $businessUnitQuery, $id);
            if ($fixed_asset === "Not yet fully depreciated") {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'end_depreciation' => [
                            $fixed_asset
                        ]
                    ]
                ], 422
                );
            }
            //return the fixed asset and formula
            return response()->json([
                'message' => 'Fixed Asset updated successfully.',
                'data' => $fixed_asset,
            ], 201);


        } else {
            return response()->json([
                'message' => 'Fixed Asset Route Not Found.'
            ], 404);
        }
    }


    public function archived(FixedAssetRequest $request, $id)
    {

        $status = $request->status;
        $remarks = ucwords($request->remarks);
        $fixedAsset = FixedAsset::query();
        $formula = Formula::query();
        if (!$fixedAsset->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
        }

        if ($status == false) {
            if (!FixedAsset::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {

                $depreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
                $fixedAssetExists = FixedAsset::where('id', $id)->where('depreciation_status_id', $depreciationStatusId)->first();

                if ($fixedAssetExists) {
                    return response()->json(['errors' => 'Unable to Archive!, Depreciation is Running!'], 422);
                }

                //if fixed asset was tag then don't allow archiving
                $additionalCosts = AdditionalCost::where('fixed_asset_id', $id)->first();
                if ($additionalCosts) {
                    return response()->json(['errors' => 'Fixed Asset was Tagged!'], 422);
                }


//                foreach($additionalCosts as $additionalCost)  {
//                    // Get the associated Formula and delete it.
//                    $formula = $additionalCost->formula;
//                    if($formula) {
//                        $formula->delete();
//                    }
//
//                    // Then, it's safe to delete the AdditionalCost.
//                    $additionalCost->delete();
//                }

                $fixedAsset->where('id', $id)->update(['remarks' => $remarks, 'is_active' => false]);
                Formula::where('id', $fixedAsset->where('id', $id)->first()->formula_id)->delete();
                $fixedAsset->where('id', $id)->delete();
//                $formula->where('fixed_asset_id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactivated!'], 200);
            }
        }
        if ($status == true) {
            if (FixedAsset::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                if (FixedAsset::where('id', $id)->where('sub_capex_id', null)->exists()) {
                    $checkSubCapex = SubCapex::where('id', $fixedAsset->where('id', $id)->first()->sub_capex_id)->exists();
                    if (!$checkSubCapex) {
                        return response()->json(['errors' => 'Unable to Restore!, SubCapex was Archived!'], 422);
                    }
                }

                $checkMinorCategory = MinorCategory::where('id', $fixedAsset->where('id', $id)->first()->minor_category_id)->exists();
                if (!$checkMinorCategory) {
                    return response()->json(['errors' => 'Unable to Restore!, Minor Category was Archived!'], 422);
                }

                //typeofrequest
                $checkTypeOfRequest = TypeOfRequest::where('id', $fixedAsset->where('id', $id)->first()->type_of_request_id)->exists();
                if (!$checkTypeOfRequest) {
                    return response()->json(['errors' => 'Unable to Restore!, Type of Request was Archived!'], 422);
                }

                $fixedAsset->withTrashed()->where('id', $id)->restore();
                $fixedAsset->update(['is_active' => true]);
                $fixedAsset->where('id', $id)->update(['remarks' => null]);
                Formula::withTrashed()->where('id', FixedAsset::where('id', $id)->first()->formula_id)->restore();
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
        }
    }


    public function search(Request $request)
    {
//        return FixedAsset::useFilters()->dynamicPaginate();

        $search = $request->get('search');
        $per_page = $request->get('per_page');
        $page = $request->get('page');
        $status = $request->get('status');
        $filter = $request->get('filter');
        return $this->fixedAssetRepository->searchFixedAsset($search, $status, $page, $per_page, $filter);
    }

    //todo change assetDescription

//    function assetDepreciation(Request $request, $id)
//    {
//        //validation
//        $validator = Validator::make($request->all(), [
//            'date' => 'required|date_format:Y-m',
//        ],
//            [
//                'date.required' => 'Date is required.',
//                'date.date_format' => 'Date format is invalid.',
//            ]);
//
//        $fixedAsset = FixedAsset::with('formula')->where('id', $id)->first();
//        if (!$fixedAsset) {
//            return response()->json([
//                'message' => 'Route not found.'
//            ], 404);
//        }
//
//        //Variable declaration
//        $depreciation_method = $fixedAsset->depreciation_method;
//        $est_useful_life = $fixedAsset->majorCategory->est_useful_life;
//        $start_depreciation = $fixedAsset->formula->start_depreciation;
//        $depreciable_basis = $fixedAsset->formula->depreciable_basis;
//        $scrap_value = $fixedAsset->formula->scrap_value;
//        $end_depreciation = $fixedAsset->formula->end_depreciation;
//        $release_date = $fixedAsset->formula->release_date;
//        $custom_end_depreciation = $validator->validated()['date'];
//
//        if ($custom_end_depreciation == date('Y-m')) {
//            if ($custom_end_depreciation > $end_depreciation) {
//                $custom_end_depreciation = $end_depreciation;
//            }
//        } elseif (!$this->calculationRepository->dateValidation($custom_end_depreciation, $start_depreciation, $fixedAsset->formula->end_depreciation)) {
//            return response()->json([
//                'message' => 'Date is invalid.'
//            ], 422);
//        }
//
//        //calculation variables
//        $custom_age = $this->calculationRepository->getMonthDifference($start_depreciation, $custom_end_depreciation);
//        $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life);
//        $yearly_depreciation = $this->calculationRepository->getYearlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life);
//        $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $custom_age);
//        $remaining_book_value = $this->calculationRepository->getRemainingBookValue($depreciable_basis, $accumulated_cost);
//
//
//        if ($fixedAsset->depreciation_method == 'One Time') {
//            $age = 0.083333333333333;
//            $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($depreciable_basis, $scrap_value, $age);
//            return response()->json([
//                'message' => 'Depreciation retrieved successfully.',
//                'data' => [
//                    'depreciation_method' => $depreciation_method,
//                    'depreciable_basis' => $depreciable_basis,
//                    'start_depreciation' => $start_depreciation,
//                    'end_depreciation' => $end_depreciation,
//                    'depreciation' => $monthly_depreciation,
//                    'depreciation_per_month' => 0,
//                    'depreciation_per_year' => 0,
//                    'accumulated_cost' => 0,
//                    'remaining_book_value' => 0,
//
//                ]
//            ], 200);
//        }
//        return response()->json([
//            'message' => 'Depreciation calculated successfully',
//            'data' => [
//                'depreciation_method' => $depreciation_method,
//                'depreciable_basis' => $depreciable_basis,
//                'est_useful_life' => $est_useful_life,
//                'months_depreciated' => $custom_age,
//                'scarp_value' => $scrap_value,
//                'start_depreciation' => $start_depreciation,
//                'end_depreciation' => $end_depreciation,
//                'depreciation_per_month' => $monthly_depreciation,
//                'depreciation_per_year' => $yearly_depreciation,
//                'accumulated_cost' => $accumulated_cost,
//                'remaining_book_value' => $remaining_book_value,
//            ]
//        ], 200);
//
//    }


    function assetDepreciation(Request $request, $id)
    {


        $fixed_asset = FixedAsset::where('id', $id)->first();
        $depreciation = DepreciationHistory::where('fixed_asset_id', $id)->latest()->first();
        $depreciationHistory = $fixed_asset->depreciationHistory;

        $formula = $fixed_asset->formula;
        $actualStartDepreciation = $formula->start_depreciation;

        $est_useful_life = $fixed_asset->majorCategory->est_useful_life ?? 0;
        $est_useful_life += ($fixed_asset->added_useful_life / 12) ?? 0;

        $addedUsefulLife = ($fixed_asset->added_useful_life / 12) ?? 0;
//        $addedUsefulLife = 5 / 12;

        if ($formula->end_depreciation !== null) {
//            $end_depreciation = (date('Y-m', strtotime($formula->end_depreciation . ' + ' . ($fixed_asset->added_useful_life ?? 0) . ' years')));
            $end_depreciation = Carbon::parse($formula->end_depreciation)->addMonths($addedUsefulLife)->format('Y-m');
        } else {
            $end_depreciation = $formula->end_depreciation;
        }
//        $end_depreciation = (date('Y-m', strtotime($formula->end_depreciation . ' + ' . ($fixed_asset->added_useful_life ?? 0) . ' years')));

        if ($fixed_asset->additionalCost->isNotEmpty()) {
            $formula->start_depreciation = Carbon::parse($fixed_asset->additionalCost->last()->created_at)->addMonth()->format('Y-m');
        }


        $actualMonthsDepreciated = $this->calculationRepository->getMonthDifference($actualStartDepreciation, now()->format('Y-m')) ?? 0;
        $monthsDepreciated = $this->calculationRepository->getMonthDifference($formula->start_depreciation, now()->format('Y-m')) ?? 0;
        $yearsDepreciated = floor(($monthsDepreciated + $actualMonthsDepreciated) / 12);
        $isDepreciated = $fixed_asset->depreciation_method !== null;

        if ($fixed_asset->depreciation_method === "One Time") {

            $data = [
                'is_small_tools' => $fixed_asset->typeOfRequest->type_of_request_name === 'Small Tools' ? 1 : 0,
                'has_history' => $isDepreciated ? $depreciationHistory->isNotEmpty() : null,
                'fixed_asset_id' => $fixed_asset->id,
                'depreciated_date' => now()->format('Y-m'),
                'est_useful_life' => round($est_useful_life, 2),
//                'remaining_useful_life' => $est_useful_life - $yearsDepreciated,
                'months_depreciated' => $monthsDepreciated + $actualMonthsDepreciated,
                'depreciation_per_month' => $depreciation->depreciation_per_month ?? 0,
                'depreciation_per_year' => $depreciation->depreciation_per_year ?? 0,
                'accumulated_cost' => $depreciation->accumulated_cost ?? 0,
                'remaining_book_value' => $depreciation->remaining_book_value ?? 0,
                'depreciable_basis' => $depreciation->depreciation_basis ?? 0,
                'acquisition_cost' => $depreciation->acquisition_cost ?? $formula->acquisition_cost,
                'start_depreciation' => $actualStartDepreciation ?? '-',
                'end_depreciation' => $end_depreciation ?? '-',
                'scrap_value' => $formula->scrap_value,
                'depreciation_method' => $fixed_asset->depreciation_method ?? '-',
                'acquisition_date' => $fixed_asset->acquisition_date,
                'major_category' => [
                    'id' => $fixed_asset->majorCategory->id ?? '-',
                    'major_category_code' => $fixed_asset->majorCategory->major_category_code ?? '-',
                    'major_category_name' => $fixed_asset->majorCategory->major_category_name ?? '-',
                ],
                'minor_category' => [
                    'id' => $fixed_asset->minorCategory->id ?? '-',
                    'minor_category_code' => $fixed_asset->minorCategory->minor_category_code ?? '-',
                    'minor_category_name' => $fixed_asset->minorCategory->minor_category_name ?? '-',
                ],
                'company' => [
                    'id' => $fixed_asset->company->id ?? '-',
                    'company_code' => $fixed_asset->company->company_code ?? '-',
                    'company_name' => $fixed_asset->company->company_name ?? '-',
                ],
                'business_unit' => [
                    'id' => $fixed_asset->businessUnit->id ?? '-',
                    'business_unit_code' => $fixed_asset->businessUnit->business_unit_code ?? '-',
                    'business_unit_name' => $fixed_asset->businessUnit->business_unit_name ?? '-',
                ],
                'department' => [
                    'id' => $fixed_asset->department->id ?? '-',
                    'department_code' => $fixed_asset->department->department_code ?? '-',
                    'department_name' => $fixed_asset->department->department_name ?? '-',
                ],
                'unit' => [
                    'id' => $fixed_asset->unit->id ?? '-',
                    'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                    'unit_name' => $fixed_asset->unit->unit_name ?? '-',
                ],
                'sub_unit' => [
                    'id' => $fixed_asset->subUnit->id ?? '-',
                    'subunit_code' => $fixed_asset->subUnit->sub_unit_code ?? '-',
                    'subunit_name' => $fixed_asset->subUnit->sub_unit_name ?? '-',
                ],
                'location' => [
                    'id' => $fixed_asset->location->id ?? '-',
                    'location_code' => $fixed_asset->location->location_code ?? '-',
                    'location_name' => $fixed_asset->location->location_name ?? '-',
                ],
                'initial_debit' => [
                    'id' => $fixed_asset->accountingEntries->initialDebit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->initialDebit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->initialDebit->account_title_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->initialDebit->account_title_name ?? '-',
                    'depreciation_debit' => $fixed_asset->accountingEntries->initialDebit->depreciationDebit ?? '-'
                ],
                'initial_credit' => [
                    'id' => $fixed_asset->accountingEntries->initialCredit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->initialCredit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->initialCredit->credit_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->initialCredit->credit_name ?? '-',
                ],
                'depreciation_debit' => $fixed_asset->depreciation_method !== null ? [
                    'id' => $fixed_asset->accountingEntries->depreciationDebit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->depreciationDebit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->depreciationDebit->account_title_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->depreciationDebit->account_title_name ?? '-',
                ] : null,
                'depreciation_credit' => [
                    'id' => $fixed_asset->accountingEntries->depreciationCredit->id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->depreciationCredit->credit_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->depreciationCredit->credit_name ?? '-',
                ],
                'second_depreciation_debit' => [
                    'id' => $fixed_asset->accountingEntries->secondDepreciationDebit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->secondDepreciationDebit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->secondDepreciationDebit->account_title_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->secondDepreciationDebit->account_title_name ?? '-',
                ],
                'second_depreciation_credit' => [
                    'id' => $fixed_asset->accountingEntries->secondDepreciationCredit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->secondDepreciationCredit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->secondDepreciationCredit->account_title_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->secondDepreciationCredit->account_title_name ?? '-',
                ],
            ];
        } else {

            $data = [
                'is_small_tools' => $fixed_asset->typeOfRequest->type_of_request_name === 'Small Tools' ? 1 : 0,
                'has_history' => $isDepreciated ? $depreciationHistory->isNotEmpty() : null,
                'fixed_asset_id' => $fixed_asset->id,
                'depreciated_date' => now()->format('Y-m'),
                'est_useful_life' => round($est_useful_life, 2),
//                'remaining_useful_life' => (($est_useful_life * 12) - $yearsDepreciated),
//                'months_depreciated' => $monthsDepreciated + $actualMonthsDepreciated,
                'months_depreciated' => $depreciation->months_depreciated ?? 0,
                'depreciation_per_month' => $depreciation->depreciation_per_month ?? 0,
                'depreciation_per_year' => $depreciation->depreciation_per_year ?? 0,
                'accumulated_cost' => $depreciation->accumulated_cost ?? 0,
                'remaining_book_value' => $depreciation->remaining_book_value ?? 0,
                'depreciable_basis' => $depreciation->depreciation_basis ?? 0,
                'acquisition_cost' => $depreciation->acquisition_cost ?? $formula->acquisition_cost,
                'start_depreciation' => $actualStartDepreciation ?? '-',
                'end_depreciation' => $end_depreciation ?? '-',
                'scrap_value' => $formula->scrap_value,
                'depreciation_method' => $fixed_asset->depreciation_method ?? '-',
                'acquisition_date' => $fixed_asset->acquisition_date,
                'major_category' => [
                    'id' => $fixed_asset->majorCategory->id ?? '-',
                    'major_category_code' => $fixed_asset->majorCategory->major_category_code ?? '-',
                    'major_category_name' => $fixed_asset->majorCategory->major_category_name ?? '-',
                ],
                'minor_category' => [
                    'id' => $fixed_asset->minorCategory->id ?? '-',
                    'minor_category_code' => $fixed_asset->minorCategory->minor_category_code ?? '-',
                    'minor_category_name' => $fixed_asset->minorCategory->minor_category_name ?? '-',
                ],
                'company' => [
                    'id' => $fixed_asset->company->id ?? '-',
                    'company_code' => $fixed_asset->company->company_code ?? '-',
                    'company_name' => $fixed_asset->company->company_name ?? '-',
                ],
                'business_unit' => [
                    'id' => $fixed_asset->businessUnit->id ?? '-',
                    'business_unit_code' => $fixed_asset->businessUnit->business_unit_code ?? '-',
                    'business_unit_name' => $fixed_asset->businessUnit->business_unit_name ?? '-',
                ],
                'department' => [
                    'id' => $fixed_asset->department->id ?? '-',
                    'department_code' => $fixed_asset->department->department_code ?? '-',
                    'department_name' => $fixed_asset->department->department_name ?? '-',
                ],
                'unit' => [
                    'id' => $fixed_asset->unit->id ?? '-',
                    'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                    'unit_name' => $fixed_asset->unit->unit_name ?? '-',
                ],
                'sub_unit' => [
                    'id' => $fixed_asset->subUnit->id ?? '-',
                    'subunit_code' => $fixed_asset->subUnit->sub_unit_code ?? '-',
                    'subunit_name' => $fixed_asset->subUnit->sub_unit_name ?? '-',
                ],
                'location' => [
                    'id' => $fixed_asset->location->id ?? '-',
                    'location_code' => $fixed_asset->location->location_code ?? '-',
                    'location_name' => $fixed_asset->location->location_name ?? '-',
                ],
                'initial_debit' => [
                    'id' => $fixed_asset->accountingEntries->initialDebit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->initialDebit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->initialDebit->account_title_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->initialDebit->account_title_name ?? '-',
                    'depreciation_debit' => $fixed_asset->accountingEntries->initialDebit->depreciationDebit ?? '-'
                ],
                'initial_credit' => [
                    'id' => $fixed_asset->accountingEntries->initialCredit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->initialCredit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->initialCredit->credit_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->initialCredit->credit_name ?? '-',
                ],
                'depreciation_debit' => $fixed_asset->depreciation_method !== null ? [
                    'id' => $fixed_asset->accountingEntries->depreciationDebit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->depreciationDebit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->depreciationDebit->account_title_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->depreciationDebit->account_title_name ?? '-',
                ] : null,
                'depreciation_credit' => [
                    'id' => $fixed_asset->accountingEntries->depreciationCredit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->depreciationCredit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->depreciationCredit->credit_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->depreciationCredit->credit_name ?? '-',
                ],
                'second_depreciation_debit' => [
                    'id' => $fixed_asset->accountingEntries->secondDepreciationDebit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->secondDepreciationDebit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->secondDepreciationDebit->account_title_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->secondDepreciationDebit->account_title_name ?? '-',
                ],
                'second_depreciation_credit' => [
                    'id' => $fixed_asset->accountingEntries->secondDepreciationCredit->id ?? '-',
                    'sync_id' => $fixed_asset->accountingEntries->secondDepreciationCredit->sync_id ?? '-',
                    'account_title_code' => $fixed_asset->accountingEntries->secondDepreciationCredit->account_title_code ?? '-',
                    'account_title_name' => $fixed_asset->accountingEntries->secondDepreciationCredit->account_title_name ?? '-',
                ],
            ];


        }
        return $this->responseSuccess('Depreciation calculated successfully', $data);

        /*        $validator = $this->validateRequest($request);
                $fixedAsset = $this->getFixedAsset($id);

                if (!$fixedAsset) {
                    return $this->buildResponse('Route not found.', 404);
                }

                $validationState = $this->checkDateValidation($validator, $fixedAsset);

                if (!$validationState['status']) {
                    return $this->buildResponse($validationState['message'], 422);
                }

                $calculationData = $this->calculateDepreciation($validator, $fixedAsset);

                if ($fixedAsset->depreciation_method === 'One Time') {
                    return $this->buildResponse('Depreciation retrieved successfully.', 200, $calculationData['onetime']);
                }

                return $this->buildResponse('Depreciation calculated successfully', 200, $calculationData['default']);*/
    }

    function validateRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m',
        ],
            [
                'date.required' => 'Date is required.',
                'date.date_format' => 'Date format is invalid.',
            ]);
    }

    function getFixedAsset($id)
    {
        return FixedAsset::with('formula')->where('id', $id)->first();
    }

    function checkDateValidation($validator, $fixedAsset): array
    {
        $custom_end_depreciation = $validator->validated()['date'];
        $end_depreciation = $fixedAsset->formula->end_depreciation;
        $start_depreciation = $fixedAsset->formula->start_depreciation;

        if ($custom_end_depreciation === date('Y-m') && $custom_end_depreciation > $end_depreciation) {
            $custom_end_depreciation = $end_depreciation;
        } elseif (!$this->calculationRepository->dateValidation($custom_end_depreciation, $start_depreciation, $end_depreciation)) {
            return [
                'status' => false,
                'message' => 'Date is invalid.'
            ];
        }

        return [
            'status' => true,
            'message' => 'Valid date.'
        ];
    }

    /*    function calculateDepreciation($validator, $fixedAsset): array
        {
            // Extract validated date
            $date = $validator->validated()['date'];
            // Extract necessary properties from the fixed asset
            $formula = $fixedAsset->formula;
            $additionalCosts = $fixedAsset->additionalCost;
            $depreciationHistory = $fixedAsset->depreciationHistory;
            $depreciationMethod = $fixedAsset->depreciation_method;
            $est_useful_life = $fixedAsset->majorCategory->est_useful_life ?? 0;
            $est_useful_life += $fixedAsset->added_useful_life;

            // Calculate the actual months depreciated
            $actualMonthsDepreciated = $this->calculationRepository->getMonthDifference($formula->start_depreciation, $date) ?? 0;

            // Calculate the end depreciation date
            $end_depreciation = (date('Y-m', strtotime($formula->end_depreciation . ' + ' . ($fixedAsset->added_useful_life ?? 0) . ' years')));

            // Filter out disposed additional costs
            //do not fetch the additional cost that has asset status of disposed
            $goodAdditionalCost = $additionalCosts->filter(function ($additionalCost) {
                return $additionalCost->assetStatus->asset_status_name !== 'Disposed';
            });

            // Get the remaining book value from the last depreciation history entry
            $remainingBookValue = $depreciationHistory->last()->book_value ?? 0;

            // If the asset is not fully depreciated and has no additional costs, use the acquisition cost
            if ($fixedAsset->depreciationStatus->depreciation_status_name !== 'Fully Depreciated' && $fixedAsset->additionalCost->isEmpty()) {
                $remainingBookValue = $formula->acquisition_cost;
            }

            // Update the start depreciation date if there are additional costs
            if ($fixedAsset->additionalCost->isNotEmpty()) {
                $formula->start_depreciation = Carbon::parse($fixedAsset->additionalCost->last()->created_at)->addMonth()->format('Y-m');
            }

            // Calculate the total of good additional costs
            $goodAddCostTotal = $goodAdditionalCost->sum('acquisition_cost') ?? 0;

            // Calculate the depreciation value
            $depreciationValue = $goodAddCostTotal + $remainingBookValue;

            // Calculate the total acquisition cost
            $totalAcquisitionCost = $formula->acquisition_cost + $additionalCosts->sum('acquisition_cost');

            // Calculate the months depreciated
            $monthsDepreciated = $this->calculationRepository->getMonthDifference($formula->start_depreciation, $date) ?? 0;

            // Calculate monthly and yearly depreciation
            $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($depreciationValue, $formula->scrap_value, $est_useful_life) ?? 0;
            $yearly_depreciation = $this->calculationRepository->getYearlyDepreciation($depreciationValue, $formula->scrap_value, $est_useful_life) ?? 0;

            // Calculate accumulated cost and remaining book value
            $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $monthsDepreciated, $depreciationValue) ?? 0;
            $remainingBookValue = $this->calculationRepository->getRemainingBookValue($depreciationValue, $accumulated_cost) ?? 0;

            // Special handling for 'One Time' depreciation method
            if ($depreciationMethod === 'One Time') {
                $age = 0.083333333333333;
                $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($depreciationValue, $formula->scrap_value, $age);
                $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $monthsDepreciated, $depreciationValue) ?? 0;
            }

            // Check if the asset is depreciated
            $isDepreciated = $fixedAsset->depreciation_method !== null;

            // Return the calculated depreciation data
            return [
                'onetime' => [
                    'depreciation_method' => $isDepreciated ? $depreciationMethod : '-',
                    'has_history' => $isDepreciated ? $depreciationHistory->isNotEmpty() : null,
                    'depreciable_basis' => $isDepreciated ? $depreciationValue : 0,
                    'est_useful_life' => 'One Time',
                    'months_depreciated' => $isDepreciated ? $monthsDepreciated : 0,
                    'scrap_value' => $formula->scrap_value ?? '-',
                    'depreciated' => $monthly_depreciation,
                    'depreciation_per_month' => $isDepreciated ? $monthly_depreciation : 0,
                    'depreciation_per_year' => $isDepreciated ? $yearly_depreciation : 0,
                    'accumulated_cost' => $isDepreciated ? $accumulated_cost : 0,
                    'remaining_book_value' => $isDepreciated ? $remainingBookValue : 0,
                    'acquisition_date' => $formula->acquisition_date ?? '-',
                    'acquisition_cost' => $totalAcquisitionCost ?? '-',
                    'initial_debit' => [
                        'id' => $fixedAsset->accountTitle->initialDebit->id ?? '-',
                        'account_title_code' => $fixedAsset->accountTitle->initialDebit->account_title_code ?? '-',
                        'account_title_name' => $fixedAsset->accountTitle->initialDebit->account_title_name ?? '-',
                    ],
                    'initial_credit' => [
                        'id' => $fixedAsset->accountTitle->initialCredit->id ?? '-',
                        'account_title_code' => $fixedAsset->accountTitle->initialCredit->account_title_code ?? '-',
                        'account_title_name' => $fixedAsset->accountTitle->initialCredit->account_title_name ?? '-',
                    ],
                    'depreciation_debit' => $fixedAsset->depreciation_method !== null ? [
                        'id' => $fixedAsset->accountTitle->depreciationDebit->id ?? '-',
                        'account_title_code' => $fixedAsset->accountTitle->depreciationDebit->account_title_code ?? '-',
                        'account_title_name' => $fixedAsset->accountTitle->depreciationDebit->account_title_name ?? '-',
                    ] : null,
                    'depreciation_credit' => $fixedAsset->depreciation_method !== null ? [
                        'id' => $fixedAsset->accountTitle->depreciationCredit->id ?? '-',
                        'account_title_code' => $fixedAsset->accountTitle->depreciationCredit->account_title_code ?? '-',
                        'account_title_name' => $fixedAsset->accountTitle->depreciationCredit->account_title_name ?? '-',
                    ] : null,
                ],
                'default' => [
                    'depreciation_method' => $isDepreciated ? $depreciationMethod : '-',
                    'has_history' => $isDepreciated ? $depreciationHistory->isNotEmpty() : null,
                    'depreciable_basis' => $isDepreciated ? $depreciationValue : 0,
                    'est_useful_life' => $est_useful_life,
                    'months_depreciated' => $isDepreciated ? $actualMonthsDepreciated : 0,
                    'scrap_value' => $formula->scrap_value ?? '-',
                    'start_depreciation' => $isDepreciated ? $formula->start_depreciation : '-',
                    'end_depreciation' => $isDepreciated ? $end_depreciation : '-',
                    'depreciation_per_month' => $isDepreciated ? $monthly_depreciation : 0,
                    'depreciation_per_year' => $isDepreciated ? $yearly_depreciation : 0,
                    'accumulated_cost' => $isDepreciated ? $accumulated_cost : 0,
                    'remaining_book_value' => $isDepreciated ? $remainingBookValue : 0,
                    'acquisition_date' => $formula->acquisition_date ?? '-',
                    'acquisition_cost' => $totalAcquisitionCost ?? '-',
    //                'additionalCost' => $goodAdditionalCost ?? null,
    //                'addCostSum' => $goodAddCostTotal ?? null,
                    'initial_debit' => [
                        'id' => $fixedAsset->accountTitle->initialDebit->id ?? '-',
                        'account_title_code' => $fixedAsset->accountTitle->initialDebit->account_title_code ?? '-',
                        'account_title_name' => $fixedAsset->accountTitle->initialDebit->account_title_name ?? '-',
                    ],
                    'initial_credit' => [
                        'id' => $fixedAsset->accountTitle->initialCredit->id ?? '-',
                        'account_title_code' => $fixedAsset->accountTitle->initialCredit->account_title_code ?? '-',
                        'account_title_name' => $fixedAsset->accountTitle->initialCredit->account_title_name ?? '-',
                    ],
                    'depreciation_debit' => $fixedAsset->depreciation_method !== null ? [
                        'id' => $fixedAsset->accountTitle->depreciationDebit->id ?? '-',
                        'account_title_code' => $fixedAsset->accountTitle->depreciationDebit->account_title_code ?? '-',
                        'account_title_name' => $fixedAsset->accountTitle->depreciationDebit->account_title_name ?? '-',
                    ] : null,
                    'depreciation_credit' => $fixedAsset->depreciation_method !== null ? [
                        'id' => $fixedAsset->accountTitle->depreciationCredit->id ?? '-',
                        'account_title_code' => $fixedAsset->accountTitle->depreciationCredit->account_title_code ?? '-',
                        'account_title_name' => $fixedAsset->accountTitle->depreciationCredit->accounst_title_name ?? '-',
                    ] : null,
                ]
            ];*/


    /*
     * todo OLD DEPRECIATION CODE
     * */
    /* //Variable declaration
     $properties = $fixedAsset->formula;
     $additionalCosts = $fixedAsset->additionalCost;
     $start_depreciation = $fixedAsset->formula->start_depreciation;
     $end_depreciation = $fixedAsset->formula->end_depreciation;
     $custom_end_depreciation = $validator->validated()['date'];
     //FOR INFORMATION
     $depreciation_method = $properties->depreciation_method ?? null;

     $est_useful_life = $fixedAsset->majorCategory->est_useful_life ?? 0;


     $acquisition_cost = $properties->acquisition_cost;

     $acquisition_date = $properties->acquisition_date ?? null;
     $scrap_value = $properties->scrap_value ?? null;

     //calculation variables

     $custom_age = $this->calculationRepository->getMonthDifference($properties->start_depreciation, $custom_end_depreciation) ?? null;

     $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($depreciationCost, $properties->scrap_value, $est_useful_life) ?? null;
     $yearly_depreciation = $this->calculationRepository->getYearlyDepreciation($depreciationCost, $properties->scrap_value, $est_useful_life) ?? null;

     $accumulated_cost_after_depreciation = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $custom_age, $acquisition_cost) ?? null;

     $remaining_book_value = $this->calculationRepository->getRemainingBookValue($depreciationCost, $accumulated_cost) ?? null;

     if ($depreciation_method === 'One Time') {
         $age = 0.083333333333333;
         $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($depreciationCost, $properties->scrap_value, $age);
         $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $custom_age, $properties->depreciable_basis) ?? null;
     }

     $isDepreciated = $fixedAsset->depreciation_method !== null;

//        if (!$isDepreciated) {
//            return [];
//        }

     return [
         'onetime' => [
             'depreciation_method' => $isDepreciated ? $depreciation_method : '-',
             'has_history' => $isDepreciated ? $fixedAsset->depreciationHistory->isNotEmpty() : null,
             'depreciable_basis' => $isDepreciated ? $acquisition_cost : 0,
             'est_useful_life' => 'One Time',
             'months_depreciated' => $isDepreciated ? $custom_age : 0,
             'scrap_value' => $scrap_value ?? '-',
             'depreciated' => $monthly_depreciation,
             'depreciation_per_month' => $isDepreciated ? $monthly_depreciation : 0,
             'depreciation_per_year' => $isDepreciated ? $yearly_depreciation : 0,
             'accumulated_cost' => $isDepreciated ? $accumulated_cost : 0,
             'remaining_book_value' => $isDepreciated ? $remaining_book_value : 0,
             'acquisition_date' => $acquisition_date ?? '-',
             'acquisition_cost' => $acquisition_cost ?? '-',
             'initial_debit' => [
                 'id' => $fixedAsset->accountTitle->initialDebit->id ?? '-',
                 'account_title_code' => $fixedAsset->accountTitle->initialDebit->account_title_code ?? '-',
                 'account_title_name' => $fixedAsset->accountTitle->initialDebit->account_title_name ?? '-',
             ],
             'initial_credit' => [
                 'id' => $fixedAsset->accountTitle->initialCredit->id ?? '-',
                 'account_title_code' => $fixedAsset->accountTitle->initialCredit->account_title_code ?? '-',
                 'account_title_name' => $fixedAsset->accountTitle->initialCredit->account_title_name ?? '-',
             ],
             'depreciation_debit' => $fixedAsset->depreciation_method !== null ? [
                 'id' => $fixedAsset->accountTitle->depreciationDebit->id ?? '-',
                 'account_title_code' => $fixedAsset->accountTitle->depreciationDebit->account_title_code ?? '-',
                 'account_title_name' => $fixedAsset->accountTitle->depreciationDebit->account_title_name ?? '-',
             ] : null,
             'depreciation_credit' => $fixedAsset->depreciation_method !== null ? [
                 'id' => $fixedAsset->accountTitle->depreciationCredit->id ?? '-',
                 'account_title_code' => $fixedAsset->accountTitle->depreciationCredit->account_title_code ?? '-',
                 'account_title_name' => $fixedAsset->accountTitle->depreciationCredit->account_title_name ?? '-',
             ] : null,
         ],
         'default' => [
             'depreciation_method' => $isDepreciated ? $depreciation_method : '-',
             'has_history' => $isDepreciated ? $fixedAsset->depreciationHistory->isNotEmpty() : null,
             'depreciable_basis' => $isDepreciated ? $acquisition_cost : 0,
             'est_useful_life' => $est_useful_life,
             'months_depreciated' => $isDepreciated ? $custom_age : 0,
             'scrap_value' => $scrap_value ?? '-',
             'start_depreciation' => $isDepreciated ? $properties->start_depreciation : '-',

             'end_depreciation' => $isDepreciated ? $end_depreciation : '-',
             'depreciation_per_month' => $isDepreciated ? $monthly_depreciation : 0,
             'depreciation_per_year' => $isDepreciated ? $yearly_depreciation : 0,
             'accumulated_cost' => $isDepreciated ? $accumulated_cost : 0,
             'remaining_book_value' => $isDepreciated ? $remaining_book_value : 0,
             'acquisition_date' => $acquisition_date ?? '-',
             'acquisition_cost' => $acquisition_cost ?? '-',
//                'additionalCost' => $additionalCosts ?? null,
             'addCostSum' => $add ?? null,
             'initial_debit' => [
                 'id' => $fixedAsset->accountTitle->initialDebit->id ?? '-',
                 'account_title_code' => $fixedAsset->accountTitle->initialDebit->account_title_code ?? '-',
                 'account_title_name' => $fixedAsset->accountTitle->initialDebit->account_title_name ?? '-',
             ],
             'initial_credit' => [
                 'id' => $fixedAsset->accountTitle->initialCredit->id ?? '-',
                 'account_title_code' => $fixedAsset->accountTitle->initialCredit->account_title_code ?? '-',
                 'account_title_name' => $fixedAsset->accountTitle->initialCredit->account_title_name ?? '-',
             ],
             'depreciation_debit' => $fixedAsset->depreciation_method !== null ? [
                 'id' => $fixedAsset->accountTitle->depreciationDebit->id ?? '-',
                 'account_title_code' => $fixedAsset->accountTitle->depreciationDebit->account_title_code ?? '-',
                 'account_title_name' => $fixedAsset->accountTitle->depreciationDebit->account_title_name ?? '-',
             ] : null,
             'depreciation_credit' => $fixedAsset->depreciation_method !== null ? [
                 'id' => $fixedAsset->accountTitle->depreciationCredit->id ?? '-',
                 'account_title_code' => $fixedAsset->accountTitle->depreciationCredit->account_title_code ?? '-',
                 'account_title_name' => $fixedAsset->accountTitle->depreciationCredit->accounst_title_name ?? '-',
             ] : null,
         ]
     ];*/
    /*}*/

    function buildResponse($message, $statusCode, $data = null)
    {
        $responseData = [
            'message' => $message,
        ];

        if ($data !== null) {
            $responseData['data'] = $data;
        }

        return response()->json($responseData, $statusCode);
    }

    public function showTagNumber($tagNumber)
    {
        $fixed_asset = FixedAsset::withTrashed()->with('formula', function ($query) {
            $query->withTrashed();
        })->where('vladimir_tag_number', $tagNumber)->first();

        if (!$fixed_asset) {
            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
        }

//        //check if the selected item has a voucher number or not
//        if ($fixed_asset->voucher == null || $fixed_asset->voucher == '-') {
//            $getVoucher = $this->fixedAssetRepository->getVoucher($fixed_asset->receipt, $fixed_asset->po_number);
//            if ($getVoucher) {
//                $fixed_asset->voucher = $getVoucher['voucher_no'];
//                $fixed_asset->voucher_date = $getVoucher['voucher_date'];
//            }
//        }

        return response()->json([
            'message' => 'Fixed Asset retrieved successfully.',
            'data' => $this->fixedAssetRepository->transformSingleFixedAsset($fixed_asset)
        ], 200);
    }

    public function sampleFixedAssetDownload()
    {
        //download file from storage/sample
        $path = storage_path('app/sample/fixed_asset.xlsx');
        return response()->download($path);
    }


    //FISTO
    public function getVoucher(Request $request)
    {
        $poFromRequest = $request->query('po_no');
        $rrFromRequest = $request->query('rr_no');
        $poBatches = PoBatch::with('fistoTransaction')->where('po_no', "PO#" . $poFromRequest)->orderBy('request_id')->get();

        $poBatch = $poBatches->first(function ($poBatch) use ($rrFromRequest) {
            $rr_group = json_decode($poBatch->rr_group);
            return in_array($rrFromRequest, $rr_group);
        });

        if ($poBatch) {
            if ($poBatch->fistoTransaction->voucher_no == null || $poBatch->fistoTransaction->voucher_month == null) {
                return $this->responseNotFound('No Voucher Found');
            }
            $result = [
                'request_id' => $poBatch->request_id,
                'voucher_no' => $poBatch->fistoTransaction->voucher_no ?? null,
                'voucher_date' => $poBatch->fistoTransaction->voucher_month ?? null,
                'rr_group' => json_decode($poBatch->rr_group)
            ];
        } else {
            return $this->responseNotFound('No Voucher Found');
        }

        return $result;
    }

    public function inclusions(Request $request)
    {
        $referenceNumber = $request->input('reference_number');
        $vTagNumber = $request->input('vladimir_tag_number', null);
        $newInclusion = $request->input('inclusion');
        if ($newInclusion[0]['description'] == null || $newInclusion[0]['quantity'] == null) {

            return $this->responseUnprocessable('Inclusion description is required.');

        }


        // Retrieve all FixedAsset records with the given reference number
//        $fixedAssetsQuery = FixedAsset::where('reference_number', $referenceNumber);
        $fixedAssetsQuery = FixedAsset::where('vladimir_tag_number', $vTagNumber);
        /* if ($vTagNumber !== null) {
             $fixedAssetsQuery->where('vladimir_tag_number', $vTagNumber);
         }*/

        $fixedAssets = $fixedAssetsQuery->get();

        foreach ($fixedAssets as $fixedAsset) {


            if ($newInclusion[0]['description'] == null && $newInclusion[0]['quantity'] == null) {

                return $this->responseUnprocessable('Inclusion description is required.');

            }

            //remove inclusion if it is null
            $fixedAsset->update(['inclusion' => null]);
            // Add an id to each object in the updated inclusion array
            foreach ($newInclusion as $index => &$item) {
                $item['id'] = $index + 1;
            }
            // Update the FixedAsset model with the new inclusion data with id
            $fixedAsset->update(['inclusion' => $newInclusion]);
        }

        return $this->responseSuccess('Inclusion added successfully.');
    }

    public function removeInclusionItem(Request $request)
    {

        $referenceNumber = $request->input('reference_number');
        $vTagNumber = $request->input('vladimir_tag_number', null);

        // Retrieve all FixedAsset records with the given reference number
//        $fixedAssetsQuery = FixedAsset::where('reference_number', $referenceNumber);
        $fixedAssetsQuery = FixedAsset::where('vladimir_tag_number', $vTagNumber);
        /*       if ($vTagNumber !== null) {
                   $fixedAssetsQuery->where('vladimir_tag_number', $vTagNumber);
               }*/

        $fixedAssets = $fixedAssetsQuery->get();

        //if inclusion is already null
        if ($fixedAssets->first()->inclusion == null) {
            return $this->responseSuccess('Inclusion item is already removed.');
        }

        $fixedAssets->each(function ($fixedAsset) {
            $fixedAsset->update(['inclusion' => null]);
        });

        return $this->responseSuccess('Inclusion item removed successfully.');
    }

    public
    function setMonthlyDepreciation(Request $request)
    {
        //set monthly depreciation globally each month
        $runningDepreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
        $fixed_assets = FixedAsset::with('formula')->where('depreciation_status_id', $runningDepreciationStatusId)->get();

    }

    public function accountingEntriesInput(CreateAccountingEntriesRequest $request, $vTagNumber)
    {
//        $initialDebit = $request->input('initial_debit_id');
//        $initialCredit = $request->input('initial_credit_id');
        $depreciationDebit = $request->input('depreciation_debit_id');
        $secondDepreciationDebit = $request->input('second_depreciation_debit_id');
        $secondDepreciationCredit = $request->input('second_depreciation_credit_id');

        $majorCategoryId = $request->input('major_category_id', null);
        $minorCategoryId = $request->input('minor_category_id', null);
//        $depreciationCredit = $request->input('depreciation_credit_id');

        #COA
        $companyId = $request->input('company_id');
        $businessUnitId = $request->input('business_unit_id');
        $departmentId = $request->input('department_id');
        $unitId = $request->input('unit_id');
        $subunitId = $request->input('subunit_id');
        $locationId = $request->input('location_id');

        $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)
//            ->where('is_released', true)
            ->first();
        $fixedAssetValidation = FixedAsset::where('vladimir_tag_number', $vTagNumber)
            ->where('is_released', true)
            ->where('from_request', true)
            ->first();

        if (!$fixedAssetValidation && $fixedAsset->from_request) {
            return $this->responseUnprocessable('Fixed Asset not yet released.');
        }
        $fixedAsset->update([
            'company_id' => $companyId,
            'business_unit_id' => $businessUnitId,
            'department_id' => $departmentId,
            'unit_id' => $unitId,
            'subunit_id' => $subunitId,
            'location_id' => $locationId,
            'major_category_id' => $majorCategoryId,
            'minor_category_id' => $minorCategoryId,
        ]);

        $fixedAsset->accountingEntries()->update([
            'depreciation_debit' => $depreciationDebit,
            'second_depreciation_debit' => $secondDepreciationDebit,
            'second_depreciation_credit' => $secondDepreciationCredit,
            /*            'initial_debit' => $initialDebit,
                        'initial_credit' => $initialCredit,
                        'depreciation_credit' => $depreciationCredit,*/
        ]);


        /*        $accountingEntries = AccountingEntries::create([
                    'initial_debit' => $initialDebit,
                    'initial_credit' => $initialCredit,
                    'depreciation_debit' => $depreciationDebit,
                    'depreciation_credit' => $depreciationCredit,
                ]);*/

        $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)->first();
//            $fixedAsset->update(['account_id' => $accountingEntries->id]);

        // Call depreciateAsset method
        $this->depreciateAsset($vTagNumber);

        return $this->responseSuccess('Fixed Asset will now be depreciating.');
    }

    public function depreciateAsset($vTagNumber)
    {
        $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)->first();
        $depreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
        $acquisitionCost = $fixedAsset->acquisition_cost;
        $depreciationMethod = $acquisitionCost < 10000 ? 'One Time' : 'STL';
        $fixedAsset->update([
            'depreciation_method' => $depreciationMethod,
            'depreciation_status_id' => $depreciationStatusId
        ]);
        $additionalCosts = $fixedAsset->additionalCost ?? null;
        if ($additionalCosts) {
            $additionalCosts->each(function ($additionalCost) use ($depreciationMethod, $depreciationStatusId) {
                $additionalCost->update([
                    'depreciation_method' => $depreciationMethod,
                    'depreciation_status_id' => $depreciationStatusId
                ]);
            });
        }
        $formula = $fixedAsset->formula;
        $formula->update([
//            'start_depreciation' => $this->calculationRepository->getStartDepreciation($depreciationMethod, $formula->release_date),
            'start_depreciation' => $this->calculationRepository->getStartDepreciation($depreciationMethod, now()->format('Y-m')),
            'end_depreciation' => $this->calculationRepository->getEndDepreciation(now()->format('Y-m'), $fixedAsset->majorCategory->est_useful_life, $depreciationMethod),
            'depreciation_method' => $depreciationMethod,
        ]);
    }

    public
    function ymirFixedAsset(Request $request)
    {
        $tagNumber = $request->input('tag_number');
        $assets = FixedAsset::where('vladimir_tag_number', 'LIKE', '%' . $tagNumber . '%')
            ->get(['vladimir_tag_number', 'asset_description'])
            ->map(function ($item) {
                return [
                    'vladimir_tag_number' => $item->vladimir_tag_number,
                    'asset_description' => $item->asset_description,
                ];
            });

        return $assets;
    }

    //Report for GL system
    public function reportGL(Request $request)
    {
        $adjustmentMonth = $request->input('adjustment_month');
        if ($adjustmentMonth == null) {
            return $this->responseSuccess('Connection established.');
        }
        $perPage = $request->input('per_page', null);
        $month2 = Carbon::parse($adjustmentMonth)->format('Ym');
        /*        $fixedAssets = FixedAsset::whereNotNull('depreciation_method')
                    ->when($adjustmentMonth, function ($query) use ($adjustmentMonth) {
                        return $query->whereHas('depreciationHistory', function ($query) use ($adjustmentMonth) {
                            $query->where('depreciated_date', $adjustmentMonth);
                        });
                    })
                    ->get();*/

        $serviceProvider = User::where('employee_id', "RDFFLFI-10932")->where('username', "mjhidalgo")->first();

        $result = FixedAsset::whereNotNull('depreciation_method')
            ->when($adjustmentMonth, function ($query) use ($adjustmentMonth) {
                return $query->whereHas('depreciationHistory', function ($query) use ($adjustmentMonth) {
                    $query->where('depreciated_date', $adjustmentMonth);
                });
            })
            ->get()
            ->flatMap(function ($item) use ($adjustmentMonth, $month2, $serviceProvider) {
                $entries = [
                    [
                        // Debit
                        "syncId" => "V" . $item->id,
                        "mark1" => null,
                        "mark2" => null,
                        "assetCIP" => $item->additional_info ?? null,
                        "accountingTag" => null,
                        "transactionDate" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date . "-01")->format('Y-m-d'),
                        "clientSupplier" => null,
                        "accountTitleCode" => $item->accountingEntries->depreciationDebit->account_title_code,
                        "accountTitle" => $item->accountingEntries->depreciationDebit->account_title_name,
                        "companyCode" => $item->company->company_code,
                        "company" => $item->company->company_name,
                        "divisionCode" => $item->businessUnit->business_unit_code ?? null,
                        "division" => $item->businessUnit->business_unit_name ?? null,
                        "departmentCode" => $item->department->department_code,
                        "department" => $item->department->department_name,
                        "unitCode" => $item->unit->unit_code,
                        "unit" => $item->unit->unit_name,
                        "subUnitCode" => $item->subunit->sub_unit_code,
                        "subUnit" => $item->subunit->sub_unit_name,
                        "locationCode" => $item->location->location_code,
                        "location" => $item->location->location_name,
                        "poNumber" => $item->po_number ?? null,
                        "rrNumber" => $item->rr_number ?? null,
                        "referenceNo" => $item->from_request ? "Ymir" : "Beginning",
                        "itemCode" => $item->item_code,
                        "itemDescription" => $item->asset_description,
                        "quantity" => $item->quantity,
                        "uom" => $item->uom->uom_name,
                        "unitPrice" => $item->acquisition_cost,
                        "lineAmount" => $item->acquisition_cost,
                        "voucherJournal" => $item->voucher,
                        "accountType" => "Asset",
                        "drcr" => "debit",
                        "assetCode" => $item->tag_number ?? null,
                        "asset" => $item->vladimir_tag_number,
                        "serviceProviderCode" => $serviceProvider->employee_id ?? null,
                        "serviceProvider" => $serviceProvider->firstname . " " . $serviceProvider->lastname ?? null,
                        "boa" => "Fixed Asset Depreciation",
                        "allocation" => null,
                        "accountGroup" => null,
                        "accountSubGroup" => null,
                        "financialStatement" => null,
                        "unitResponsible" => null,
                        "batch" => null,
                        "remarks" => $item->remarks,
                        "payrollPeriod" => null,
                        "position" => null,
                        "payrollType" => null,
                        "payrollType2" => null,
                        "depreciationDescription" => $item->asset_specification,
                        "remainingDepreciationValue" => $item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->remaining_book_value ?? null,
                        "usefulLife" => $item->majorCategory->est_useful_life,
                        "month" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date)->format('M'),
                        "year" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date)->format('Y'),
                        "particulars" => null,
                        "month2" => $month2,
                        "farmType" => null,
                        "adjustment" => null,
                        "from" => null,
                        "changeTo" => null,
                        "reason" => $item->remarks,
                        "checkingRemarks" => $item->majorCategory->major_category_name,
                        "bankName" => null,
                        "chequeNumber" => null,
                        "chequeVoucherNumber" => null,
                        "chequeDate" => null,
                        "releasedDate" => $item->formula->release_date ?? null,
                        "boA2" => "Fixed Asset Depreciation",
                        "system" => "Vladimir",
                        "books" => "Journal Book",
                    ],
                    [
                        // Credit
                        "syncId" => "V" . $item->id . ".1",
                        "mark1" => null,
                        "mark2" => null,
                        "assetCIP" => $item->additional_info ?? null,
                        "accountingTag" => null,
                        "transactionDate" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date . "-01")->format('Y-m-d'),
                        "clientSupplier" => null,
                        "accountTitleCode" => $item->accountingEntries->depreciationCredit->credit_code,
                        "accountTitle" => $item->accountingEntries->depreciationCredit->credit_name,
                        "companyCode" => $item->company->company_code,
                        "company" => $item->company->company_name,
                        "divisionCode" => $item->businessUnit->business_unit_code ?? null,
                        "division" => $item->businessUnit->business_unit_name ?? null,
                        "departmentCode" => $item->department->department_code,
                        "department" => $item->department->department_name,
                        "unitCode" => $item->unit->unit_code,
                        "unit" => $item->unit->unit_name,
                        "subUnitCode" => $item->subunit->sub_unit_code,
                        "subUnit" => $item->subunit->sub_unit_name,
                        "locationCode" => $item->location->location_code,
                        "location" => $item->location->location_name,
                        "poNumber" => $item->po_number ?? null,
                        "rrNumber" => $item->rr_number ?? null,
                        "referenceNo" => $item->from_request ? "Ymir" : "Beginning",
                        "itemCode" => $item->item_code,
                        "itemDescription" => $item->asset_description,
                        "quantity" => $item->quantity,
                        "uom" => $item->uom->uom_name,
                        "unitPrice" => -1 * $item->acquisition_cost,
                        "lineAmount" => -1 * $item->acquisition_cost,
                        "voucherJournal" => $item->voucher,
                        "accountType" => "Asset",
                        "drcr" => "credit",
                        "assetCode" => $item->tag_number ?? null,
                        "asset" => $item->vladimir_tag_number,
                        "serviceProviderCode" => $serviceProvider->employee_id ?? null,
                        "serviceProvider" => $serviceProvider->firstname . " " . $serviceProvider->lastname ?? null,
                        "boa" => "Fixed Asset Depreciation",
                        "allocation" => null,
                        "accountGroup" => null,
                        "accountSubGroup" => null,
                        "financialStatement" => null,
                        "unitResponsible" => null,
                        "batch" => null,
                        "remarks" => $item->remarks,
                        "payrollPeriod" => null,
                        "position" => null,
                        "payrollType" => null,
                        "payrollType2" => null,
                        "depreciationDescription" => $item->asset_specification,
                        "remainingDepreciationValue" => $item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->remaining_book_value ?? null,
                        "usefulLife" => $item->majorCategory->est_useful_life,
                        "month" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date)->format('M'),
                        "year" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date)->format('Y'),
                        "particulars" => null,
                        "month2" => $month2,
                        "farmType" => null,
                        "adjustment" => null,
                        "from" => null,
                        "changeTo" => null,
                        "reason" => $item->remarks,
                        "checkingRemarks" => $item->majorCategory->major_category_name,
                        "bankName" => null,
                        "chequeNumber" => null,
                        "chequeVoucherNumber" => null,
                        "chequeDate" => null,
                        "releasedDate" => $item->formula->release_date ?? null,
                        "boA2" => "Fixed Asset Depreciation",
                        "system" => "Vladimir",
                        "books" => "Journal Book",
                    ],
                ];

                // Add second depreciation entries if both second_depreciation_debit and second_depreciation_credit exist
                if ($item->accountingEntries->second_depreciation_debit && $item->accountingEntries->second_depreciation_credit) {
                    // Second debit entry
                    $entries[] = [
                        "syncId" => "V" . $item->id . ".2",
                        "mark1" => null,
                        "mark2" => null,
                        "assetCIP" => $item->additional_info ?? null,
                        "accountingTag" => null,
                        "transactionDate" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date . "-01")->format('Y-m-d'),
                        "clientSupplier" => null,
                        "accountTitleCode" => $item->accountingEntries->secondDepreciationDebit->account_title_code,
                        "accountTitle" => $item->accountingEntries->secondDepreciationDebit->account_title_name,
                        "companyCode" => $item->company->company_code,
                        "company" => $item->company->company_name,
                        "divisionCode" => $item->businessUnit->business_unit_code ?? null,
                        "division" => $item->businessUnit->business_unit_name ?? null,
                        "departmentCode" => $item->department->department_code,
                        "department" => $item->department->department_name,
                        "unitCode" => $item->unit->unit_code,
                        "unit" => $item->unit->unit_name,
                        "subUnitCode" => $item->subunit->sub_unit_code,
                        "subUnit" => $item->subunit->sub_unit_name,
                        "locationCode" => $item->location->location_code,
                        "location" => $item->location->location_name,
                        "poNumber" => $item->po_number ?? null,
                        "rrNumber" => $item->rr_number ?? null,
                        "referenceNo" => $item->from_request ? "Ymir" : "Beginning",
                        "itemCode" => $item->item_code,
                        "itemDescription" => $item->asset_description,
                        "quantity" => $item->quantity,
                        "uom" => $item->uom->uom_name,
                        "unitPrice" => $item->acquisition_cost,
                        "lineAmount" => $item->acquisition_cost,
                        "voucherJournal" => $item->voucher,
                        "accountType" => "Asset",
                        "drcr" => "debit",
                        "assetCode" => $item->tag_number ?? null,
                        "asset" => $item->vladimir_tag_number,
                        "serviceProviderCode" => $serviceProvider->employee_id ?? null,
                        "serviceProvider" => $serviceProvider->firstname . " " . $serviceProvider->lastname ?? null,
                        "boa" => "Fixed Asset Depreciation",
                        "allocation" => null,
                        "accountGroup" => null,
                        "accountSubGroup" => null,
                        "financialStatement" => null,
                        "unitResponsible" => null,
                        "batch" => null,
                        "remarks" => $item->remarks,
                        "payrollPeriod" => null,
                        "position" => null,
                        "payrollType" => null,
                        "payrollType2" => null,
                        "depreciationDescription" => $item->asset_specification,
                        "remainingDepreciationValue" => $item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->remaining_book_value ?? null,
                        "usefulLife" => $item->majorCategory->est_useful_life,
                        "month" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date)->format('M'),
                        "year" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date)->format('Y'),
                        "particulars" => null,
                        "month2" => $month2,
                        "farmType" => null,
                        "adjustment" => null,
                        "from" => null,
                        "changeTo" => null,
                        "reason" => $item->remarks,
                        "checkingRemarks" => $item->majorCategory->major_category_name,
                        "bankName" => null,
                        "chequeNumber" => null,
                        "chequeVoucherNumber" => null,
                        "chequeDate" => null,
                        "releasedDate" => $item->formula->release_date ?? null,
                        "boA2" => "Fixed Asset Depreciation",
                        "system" => "Vladimir",
                        "books" => "Journal Book",
                    ];

                    // Second credit entry
                    $entries[] = [
                        "syncId" => "V" . $item->id . ".2.1  ",
                        "mark1" => null,
                        "mark2" => null,
                        "assetCIP" => $item->additional_info ?? null,
                        "accountingTag" => null,
                        "transactionDate" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date . "-01")->format('Y-m-d'),
                        "clientSupplier" => null,
                        "accountTitleCode" => $item->accountingEntries->secondDepreciationCredit->account_title_code,
                        "accountTitle" => $item->accountingEntries->secondDepreciationCredit->account_title_name,
                        "companyCode" => $item->company->company_code,
                        "company" => $item->company->company_name,
                        "divisionCode" => $item->businessUnit->business_unit_code ?? null,
                        "division" => $item->businessUnit->business_unit_name ?? null,
                        "departmentCode" => $item->department->department_code,
                        "department" => $item->department->department_name,
                        "unitCode" => $item->unit->unit_code,
                        "unit" => $item->unit->unit_name,
                        "subUnitCode" => $item->subunit->sub_unit_code,
                        "subUnit" => $item->subunit->sub_unit_name,
                        "locationCode" => $item->location->location_code,
                        "location" => $item->location->location_name,
                        "poNumber" => $item->po_number ?? null,
                        "rrNumber" => $item->rr_number ?? null,
                        "referenceNo" => $item->from_request ? "Ymir" : "Beginning",
                        "itemCode" => $item->item_code,
                        "itemDescription" => $item->asset_description,
                        "quantity" => $item->quantity,
                        "uom" => $item->uom->uom_name,
                        "unitPrice" => -1 * $item->acquisition_cost,
                        "lineAmount" => -1 * $item->acquisition_cost,
                        "voucherJournal" => $item->voucher,
                        "accountType" => "Asset",
                        "drcr" => "credit",
                        "assetCode" => $item->tag_number ?? null,
                        "asset" => $item->vladimir_tag_number,
                        "serviceProviderCode" => $serviceProvider->employee_id ?? null,
                        "serviceProvider" => $serviceProvider->firstname . " " . $serviceProvider->lastname ?? null,
                        "boa" => "Fixed Asset Depreciation",
                        "allocation" => null,
                        "accountGroup" => null,
                        "accountSubGroup" => null,
                        "financialStatement" => null,
                        "unitResponsible" => null,
                        "batch" => null,
                        "remarks" => $item->remarks,
                        "payrollPeriod" => null,
                        "position" => null,
                        "payrollType" => null,
                        "payrollType2" => null,
                        "depreciationDescription" => $item->asset_specification,
                        "remainingDepreciationValue" => $item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->remaining_book_value ?? null,
                        "usefulLife" => $item->majorCategory->est_useful_life,
                        "month" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date)->format('M'),
                        "year" => Carbon::parse($item->depreciationHistory->where('depreciated_date', $adjustmentMonth)->first()->depreciated_date)->format('Y'),
                        "particulars" => null,
                        "month2" => $month2,
                        "farmType" => null,
                        "adjustment" => null,
                        "from" => null,
                        "changeTo" => null,
                        "reason" => $item->remarks,
                        "checkingRemarks" => $item->majorCategory->major_category_name,
                        "bankName" => null,
                        "chequeNumber" => null,
                        "chequeVoucherNumber" => null,
                        "chequeDate" => null,
                        "releasedDate" => $item->formula->release_date ?? null,
                        "boA2" => "Fixed Asset Depreciation",
                        "system" => "Vladimir",
                        "books" => "Journal Book",
                    ];
                }

                return $entries;
            })->values();


        if ($perPage) {

            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            $resultArray = $result->toArray();
            return new LengthAwarePaginator(
                array_slice($resultArray, $offset, $perPage, false),
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 count($resultArray),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            )                      ;
        }
        return $result;

    }


    public function movementReports(Request $request)
    {
        $from = $request->input('from', null);
        $to = $request->input('to', null);
        $movementType = $request->input('movement_type', null);
        if ($from) {
            $from = Carbon::parse($from)->startOfDay();
        }
        if ($to) {
            $to = Carbon::parse($to)->endOfDay();
        }


        switch (ucwords($movementType)) {
            case 'Transfer':
                $movementHistory = AssetMovementHistory::whereHasMorph('movementHistory', Transfer::class);
                break;
            case 'Pullout':
                $movementHistory = AssetMovementHistory::whereHasMorph('movementHistory', PullOut::class);
                break;
            default:
                $movementHistory = AssetMovementHistory::whereHasMorph('movementHistory', [Transfer::class, PullOut::class]);
                break;
        }

        $movementHistory = $movementHistory->when($from, function ($query) use ($from) {
            return $query->where('created_at', '>=', $from);
        })->when($to, function ($query) use ($to) {
            return $query->where('created_at', '<=', $to);
        })->orderBy('created_at')->dynamicPaginate();


        $movementHistory->transform(function ($item) {
            $movementHistory = $item->movementHistory;
//            $item->accountable ?:
            $movementTypes = [
                'App\\Models\\Transfer' => 'Transfer',
                'App\\Models\\PullOut' => 'Pull Out',
                'App\\Models\\Disposal' => 'Disposal',
                // Add more mappings as needed
            ];
            $item->from = $item->requester->employee_id . ' - ' . $item->requester->firstname . ' ' . $item->requester->lastname;
            $item->to = $movementHistory->receiver->employee_id . ' - ' . $movementHistory->receiver->firstname . ' ' . $movementHistory->receiver->lastname;
            return [
                'id' => $item->id,
                'vladimir_tag_number' => $movementHistory->fixedAsset->vladimir_tag_number ?? '-',
                'asset_description' => $movementHistory->fixedAsset->asset_description ?? '-',
                'from' => $item->from ?? '-',
                'to' => $item->to ?? '-',
                'movement_type' => $movementTypes[$item->subject_type] ?? $item->subject_type,
                'status' => $item->fixedAsset->assetStatus->asset_status_name ?? '-',
                'created_at' => $item->created_at ?? '-',
            ];
        });

        return $movementHistory;
    }


    /*    public function depreciationViewing(Request $request, $id)
        {
            $fixed_asset = FixedAsset::where('id', $id)->first();
            $depreciation = DepreciationHistory::where('fixed_asset_id', $id)->latest()->first();

            $formula = $fixed_asset->formula;
            $actualStartDepreciation = $formula->start_depreciation;

            $est_useful_life = $fixed_asset->majorCategory->est_useful_life ?? 0;
            $est_useful_life += $fixed_asset->added_useful_life;

            $end_depreciation = (date('Y-m', strtotime($formula->end_depreciation . ' + ' . ($fixed_asset->added_useful_life ?? 0) . ' years')));


            if ($fixed_asset->additionalCost->isNotEmpty()) {
                $formula->start_depreciation = Carbon::parse($fixed_asset->additionalCost->last()->created_at)->addMonth()->format('Y-m');
            }


            $actualMonthsDepreciated = $this->calculationRepository->getMonthDifference($actualStartDepreciation, now()->format('Y-m')) ?? 0;
            $monthsDepreciated = $this->calculationRepository->getMonthDifference($formula->start_depreciation, now()->format('Y-m')) ?? 0;
            $yearsDepreciated = floor(($monthsDepreciated + $actualMonthsDepreciated) / 12);


            if ($fixed_asset->depreciation_method == "One Time") {
                return [
                    'fixed_asset_id' => $fixed_asset->id,
                    'depreciated_date' => now()->format('Y-m'),
                    'estimated_useful_life' => $est_useful_life,
                    'remaining_useful_life' => $est_useful_life - $yearsDepreciated,
                    'months_depreciated' => $monthsDepreciated + $actualMonthsDepreciated,
                    'depreciation_per_month' => $depreciation->depreciation_per_month,
                    'depreciation_per_year' => $depreciation->depreciation_per_year,
                    'accumulated_cost' => $depreciation->accumulated_cost,
                    'remaining_book_value' => $depreciation->remaining_book_value,
                    'depreciation_basis' => $depreciation->depreciation_basis,
                    'acquisition_cost' => $depreciation->acquisition_cost,
                    'start_depreciation' => $formula->start_depreciation,
                    'end_depreciation' => $end_depreciation,
                    'scrap_value' => $formula->scrap_value,
                    'initial_debit' => [
                        'id' => $fixed_asset->accountTitle->initialDebit->id ?? '-',
                        'account_title_code' => $fixed_asset->accountTitle->initialDebit->account_title_code ?? '-',
                        'account_title_name' => $fixed_asset->accountTitle->initialDebit->account_title_name ?? '-',
                    ],
                    'initial_credit' => [
                        'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                        'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                        'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
                    ],
                    'depreciation_debit' => $fixed_asset->depreciation_method !== null ? [
                        'id' => $fixed_asset->accountTitle->depreciationDebit->id ?? '-',
                        'account_title_code' => $fixed_asset->accountTitle->depreciationDebit->account_title_code ?? '-',
                        'account_title_name' => $fixed_asset->accountTitle->depreciationDebit->account_title_name ?? '-',
                    ] : null,
                    'depreciation_credit' => $fixed_asset->depreciation_method !== null ? [
                        'id' => $fixed_asset->accountTitle->depreciationCredit->id ?? '-',
                        'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->account_title_code ?? '-',
                        'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->account_title_name ?? '-',
                    ] : null,

                ];
            } else {
                return [
                    'fixed_asset_id' => $fixed_asset->id,
                    'depreciated_date' => now()->format('Y-m'),
                    'estimated_useful_life' => $est_useful_life,
                    'remaining_useful_life' => $est_useful_life - $yearsDepreciated,
                    'months_depreciated' => $monthsDepreciated + $actualMonthsDepreciated,
                    'depreciation_per_month' => $depreciation->depreciation_per_month,
                    'depreciation_per_year' => $depreciation->depreciation_per_year,
                    'accumulated_cost' => $depreciation->accumulated_cost,
                    'remaining_book_value' => $depreciation->remaining_book_value,
                    'depreciation_basis' => $depreciation->depreciation_basis,
                    'acquisition_cost' => $depreciation->acquisition_cost,
                    'start_depreciation' => $formula->start_depreciation,
                    'end_depreciation' => $end_depreciation,
                    'scrap_value' => $formula->scrap_value,
                    'initial_debit' => [
                        'id' => $fixed_asset->accountTitle->initialDebit->id ?? '-',
                        'account_title_code' => $fixed_asset->accountTitle->initialDebit->account_title_code ?? '-',
                        'account_title_name' => $fixed_asset->accountTitle->initialDebit->account_title_name ?? '-',
                    ],
                    'initial_credit' => [
                        'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                        'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                        'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
                    ],
                    'depreciation_debit' => $fixed_asset->depreciation_method !== null ? [
                        'id' => $fixed_asset->accountTitle->depreciationDebit->id ?? '-',
                        'account_title_code' => $fixed_asset->accountTitle->depreciationDebit->account_title_code ?? '-',
                        'account_title_name' => $fixed_asset->accountTitle->depreciationDebit->account_title_name ?? '-',
                    ] : null,
                    'depreciation_credit' => $fixed_asset->depreciation_method !== null ? [
                        'id' => $fixed_asset->accountTitle->depreciationCredit->id ?? '-',
                        'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->account_title_code ?? '-',
                        'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->account_title_name ?? '-',
                    ] : null,
                ];
            }
        }*/

}
