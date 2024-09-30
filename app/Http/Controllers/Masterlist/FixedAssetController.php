<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingEntries\CreateAccountingEntriesRequest;
use App\Http\Requests\FixedAsset\CreateSmallToolsRequest;
use App\Http\Requests\FixedAsset\FixedAssetRequest;
use App\Http\Requests\FixedAsset\FixedAssetUpdateRequest;
use App\Http\Requests\FixedAsset\MemorPrintRequest;
use App\Imports\MasterlistImport;
use App\Models\AccountingEntries;
use App\Models\AccountTitle;
use App\Models\AdditionalCost;
use App\Models\BusinessUnit;
use App\Models\Capex;
use App\Models\Company;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\MemoSeries;
use App\Models\MinorCategory;
use App\Models\PoBatch;
use App\Models\Status\DepreciationStatus;
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use App\Models\YmirPRTransaction;
use App\Repositories\CalculationRepository;
use App\Repositories\FixedAssetRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use Carbon\Carbon;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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
        $movement = $request->get('movement');
        $ymir = $request->get('ymir', false);
        $data = Cache::get('fixed_assets_data');


        return $this->fixedAssetRepository->faIndex($ymir, $movement);
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

//        return $request->all();
        $vladimirTagNumber = $this->vladimirTagGeneratorRepository->vladimirTagGenerator();
        if (!is_numeric($vladimirTagNumber) || strlen($vladimirTagNumber) != 13) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => 'Wrong vladimir tag number format. Please try again.'
            ], 422);
        }

        //minor Category check
        $majorCategory = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->first();
        $minorCategoryCheck = MinorCategory::withTrashed()->where('id', $request->minor_category_id)
            ->where('major_category_id', $majorCategory->id)->exists();

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

//        $departmentQuery = Department::with('location')->where('id', $request->department_id)->first();
        $businessUnitQuery = BusinessUnit::where('id', $request->business_unit_id)->first();
        $fixedAsset = $this->fixedAssetRepository->storeFixedAsset($request->all(), $vladimirTagNumber, $businessUnitQuery);
        if ($fixedAsset == "Not yet fully depreciated") {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'end_depreciation' => [
                        $fixedAsset
                    ]
                ]
            ], 422
            );
        }
        //return the fixed asset and formula
        return response()->json([
            'message' => 'Fixed Asset created successfully.',
            'data' => $fixedAsset
        ], 201);
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


    //TODO: Ask on what should and should not be updated on the fixed asset
    public function update(FixedAssetUpdateRequest $request, int $id)
    {
        $request->validated();
        //minor Category check
        $majorCategory = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->first()->id;
        $minorCategoryCheck = MinorCategory::withTrashed()->where('id', $request->minor_category_id)
            ->where('major_category_id', $majorCategory)->exists();

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
            if ($fixed_asset == "Not yet fully depreciated") {
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
        $validator = $this->validateRequest($request);
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

        return $this->buildResponse('Depreciation calculated successfully', 200, $calculationData['default']);
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

    function calculateDepreciation($validator, $fixedAsset): array
    {
        //Variable declaration
        $properties = $fixedAsset->formula;
        $start_depreciation = $fixedAsset->formula->start_depreciation;
        $end_depreciation = $fixedAsset->formula->end_depreciation;
        $custom_end_depreciation = $validator->validated()['date'];
        //FOR INFORMATION
        $depreciation_method = $properties->depreciation_method ?? null;
        $est_useful_life = $fixedAsset->majorCategory->est_useful_life ?? 0;
        $acquisition_date = $properties->acquisition_date ?? null;
        $acquisition_cost = $properties->acquisition_cost ?? null;
        $scrap_value = $properties->scrap_value ?? null;


        //calculation variables
        $custom_age = $this->calculationRepository->getMonthDifference($properties->start_depreciation, $custom_end_depreciation) ?? null;
        $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($properties->acquisition_cost, $properties->scrap_value, $est_useful_life) ?? null;
        $yearly_depreciation = $this->calculationRepository->getYearlyDepreciation($properties->acquisition_cost, $properties->scrap_value, $est_useful_life) ?? null;
        $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $custom_age, $properties->depreciable_basis) ?? null;
        $remaining_book_value = $this->calculationRepository->getRemainingBookValue($properties->acquisition_cost, $accumulated_cost) ?? null;

        if ($depreciation_method === 'One Time') {
            $age = 0.083333333333333;
            $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($properties->acquisition_cost, $properties->scrap_value, $age);
            $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $custom_age, $properties->depreciable_basis) ?? null;
        }

        return [
            'onetime' => [
                'depreciation_method' => $depreciation_method,
                'has_history' => $fixedAsset->depreciationHistory->isNotEmpty(),
                'depreciable_basis' => $properties->depreciable_basis,
                'start_depreciation' => $properties->start_depreciation,
                'end_depreciation' => $properties->end_depreciation,
                'depreciated' => $monthly_depreciation,
                'depreciation_per_month' => $monthly_depreciation,
                'depreciation_per_year' => 0,
                'accumulated_cost' => $accumulated_cost ?? '-',
                'remaining_book_value' => $remaining_book_value ?? '-',
                'scrap_value' => $scrap_value,
                'acquisition_date' => $acquisition_date,
                'acquisition_cost' => $acquisition_cost,
                'est_useful_life' => 'One Time',
                'months_depreciated' => $custom_age,
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
                'depreciation_method' => $depreciation_method ?? '-',
                'has_history' => $fixedAsset->depreciationHistory->isNotEmpty(),
                'depreciable_basis' => $properties->depreciable_basis ?? 0,
                'est_useful_life' => $est_useful_life ?? '-',
                'months_depreciated' => $custom_age ?? '-',
                'scrap_value' => $scrap_value ?? '-',
                'start_depreciation' => $properties->start_depreciation ?? '-',
                'end_depreciation' => $properties->end_depreciation ?? '-',
                'depreciation_per_month' => $monthly_depreciation ?? '-',
                'depreciation_per_year' => $yearly_depreciation ?? '-',
                'accumulated_cost' => $accumulated_cost ?? '-',
                'remaining_book_value' => $remaining_book_value ?? '-',
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
                    'account_title_name' => $fixedAsset->accountTitle->depreciationCredit->accounst_title_name ?? '-',
                ] : null,
            ]
        ];
    }

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

    public function showTagNumber(int $tagNumber)
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

        // Retrieve all FixedAsset records with the given reference number
        $fixedAssetsQuery = FixedAsset::where('reference_number', $referenceNumber);

        if ($vTagNumber !== null) {
            $fixedAssetsQuery->where('vladimir_tag_number', $vTagNumber);
        }

        $fixedAssets = $fixedAssetsQuery->get();

        foreach ($fixedAssets as $fixedAsset) {
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
        $fixedAssetsQuery = FixedAsset::where('reference_number', $referenceNumber);

        if ($vTagNumber !== null) {
            $fixedAssetsQuery->where('vladimir_tag_number', $vTagNumber);
        }

        $fixedAssets = $fixedAssetsQuery->get();

        $fixedAssets->each(function ($fixedAsset) {
            $fixedAsset->update(['inclusion' => null]);
        });

        return $this->responseSuccess('Inclusion item removed successfully.');
    }

    public function setMonthlyDepreciation(Request $request)
    {
        //set monthly depreciation globally each month
        $runningDepreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
        $fixed_assets = FixedAsset::with('formula')->where('depreciation_status_id', $runningDepreciationStatusId)->get();

    }

    public function accountingEntriesInput(CreateAccountingEntriesRequest $request, $vTagNumber)
    {
        $initialDebit = $request->input('initial_debit_id');
        $initialCredit = $request->input('initial_credit_id');
        $depreciationDebit = $request->input('depreciation_debit_id');
        $depreciationCredit = $request->input('depreciation_credit_id');

        $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)
            ->where('is_released', true)
            ->first();
        if (!$fixedAsset) {
            return $this->responseUnprocessable('Fixed Asset not yet released.');
        }


        $accountingEntries = AccountingEntries::create([
            'initial_debit' => $initialDebit,
            'initial_credit' => $initialCredit,
            'depreciation_debit' => $depreciationDebit,
            'depreciation_credit' => $depreciationCredit,
        ]);

        if ($accountingEntries) {
            $fixedAsset = FixedAsset::where('vladimir_tag_number', $vTagNumber)->first();
            $fixedAsset->update(['account_id' => $accountingEntries->id]);

            // Call depreciateAsset method
            $this->depreciateAsset($vTagNumber);
        }
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
        $formula = $fixedAsset->formula;
        $formula->update([
            'start_depreciation' => $this->calculationRepository->getStartDepreciation($depreciationMethod, $formula->release_date),
            'end_depreciation' => $this->calculationRepository->getEndDepreciation(now()->format('Y-m'), $fixedAsset->majorCategory->est_useful_life, $depreciationMethod),
            'depreciation_method' => $depreciationMethod,
        ]);
    }
}
