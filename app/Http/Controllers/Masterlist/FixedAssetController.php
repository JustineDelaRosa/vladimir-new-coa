<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedAsset\FixedAssetRequest;
use App\Http\Requests\FixedAsset\FixedAssetUpdateRequest;
use App\Imports\MasterlistImport;
use App\Models\AccountTitle;
use App\Models\Capex;
use App\Models\Company;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\Status\DepreciationStatus;
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use App\Repositories\CalculationRepository;
use App\Repositories\FixedAssetRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FixedAssetController extends Controller
{
    protected $fixedAssetRepository, $vladimirTagGeneratorRepository, $calculationRepository;

    public function __construct()
    {
        $this->fixedAssetRepository = new FixedAssetRepository();
        $this->vladimirTagGeneratorRepository = new VladimirTagGeneratorRepository();
        $this->calculationRepository = new CalculationRepository();
    }


    public function index()
    {
        $fixed_assets = FixedAsset::with('formula')->get();
        return response()->json([
            'message' => 'Fixed Assets retrieved successfully.',
            'data' => $this->fixedAssetRepository->transformFixedAsset($fixed_assets)
        ], 200);
    }


    public function store(FixedAssetRequest $request)
    {
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
        $departmentQuery = Department::where('id', $request->department_id)->first();
        $fixedAsset = $this->fixedAssetRepository->storeFixedAsset($request->all(), $vladimirTagNumber, $departmentQuery);

        //return the fixed asset and formula
        return response()->json([
            'message' => 'Fixed Asset created successfully.',
            'data' => $fixedAsset
        ], 201);
    }

    public function show(int $id)
    {
        $fixed_asset = FixedAsset::withTrashed()->with('formula', function ($query) {
            $query->withTrashed();
        })
            ->where('id', $id)->first();


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
        $fixedAsset = FixedAsset::where('id', $id)->first();
        if ($fixedAsset) {
            $this->fixedAssetRepository->updateFixedAsset($request->all(), $departmentQuery, $id);
            return response()->json([
                'message' => 'Fixed Asset updated successfully',
                'data' => $fixedAsset->load('formula'),
            ], 200);
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
                if(FixedAsset::where('id', $id)->where('sub_capex_id', null)->exists()) {
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
        $search = $request->get('search');
        $limit = $request->get('limit');
        $page = $request->get('page');
        $status = $request->get('status');
        return $this->fixedAssetRepository->searchFixedAsset($search, $status, $limit, $page);
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
        $depreciation_method = $fixedAsset->depreciation_method;
        $est_useful_life = $fixedAsset->majorCategory->est_useful_life;
        $custom_end_depreciation = $validator->validated()['date'];

        //calculation variables
        $custom_age = $this->calculationRepository->getMonthDifference($properties->start_depreciation, $custom_end_depreciation);
        $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($properties->depreciable_basis, $properties->scrap_value, $est_useful_life);
        $yearly_depreciation = $this->calculationRepository->getYearlyDepreciation($properties->depreciable_basis, $properties->scrap_value, $est_useful_life);
        $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $custom_age);
        $remaining_book_value = $this->calculationRepository->getRemainingBookValue($properties->depreciable_basis, $accumulated_cost);

        if ($depreciation_method === 'One Time') {
            $age = 0.083333333333333;
            $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($properties->depreciable_basis, $properties->scrap_value, $age);
        }

        return [
            'onetime' => [
                'depreciation_method' => $depreciation_method,
                'depreciable_basis' => $properties->depreciable_basis,
                'start_depreciation' => $properties->start_depreciation,
                'end_depreciation' => $properties->end_depreciation,
                'depreciation' => $monthly_depreciation,
                'depreciation_per_month' => 0,
                'depreciation_per_year' => 0,
                'accumulated_cost' => 0,
                'remaining_book_value' => 0,
            ],
            'default' => [
                'depreciation_method' => $depreciation_method,
                'depreciable_basis' => $properties->depreciable_basis,
                'est_useful_life' => $est_useful_life,
                'months_depreciated' => $custom_age,
                'scarp_value' => $properties->scrap_value,
                'start_depreciation' => $properties->start_depreciation,
                'end_depreciation' => $properties->end_depreciation,
                'depreciation_per_month' => $monthly_depreciation,
                'depreciation_per_year' => $yearly_depreciation,
                'accumulated_cost' => $accumulated_cost,
                'remaining_book_value' => $remaining_book_value,
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
}
