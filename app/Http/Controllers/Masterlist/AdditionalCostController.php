<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdditionalCost\AdditionalCostRequest;
use App\Models\AdditionalCost;
use App\Models\Department;
use App\Models\FixedAsset;
use App\Repositories\AdditionalCostRepository;
use App\Repositories\CalculationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdditionalCostController extends Controller
{
    protected $additionalCostRepository,$calculationRepository;

    public function __construct()
    {
        $this->additionalCostRepository = new AdditionalCostRepository();
        $this->calculationRepository = new CalculationRepository();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $additionalCost = AdditionalCost::with('formula')->get();
        return response()->json([
            'message' => 'Successfully fetched all additional cost!',
            'data' => $this->additionalCostRepository->transformAdditionalCost($additionalCost)
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AdditionalCostRequest $request)
    {
        $departmentQuery = Department::where('id', $request->department_id)->first();
        $additionalCost = $this->additionalCostRepository->storeAdditionalCost($request->all(), $departmentQuery);

        return response()->json([
            'message' => 'Additional Cost successfully created!',
            'data' => $additionalCost,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $additional_cost = AdditionalCost::withTrashed()->with('formula')->where('id', $id)->first();

        if(!$additional_cost) {
            return response()->json([
                'message' => 'Additional Cost route not found!',
            ], 404);
        }

        return response()->json([
            'message' => 'Successfully fetched additional cost!',
            'data' => $this->additionalCostRepository->transformSingleAdditionalCost($additional_cost),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AdditionalCostRequest $request, $id)
    {
        $request->validated();
        $departmentQuery = Department::where('id', $request->department_id)->first();
        $additionalCost = AdditionalCost::where('id', $id)->first();
        if($additionalCost) {
            $additionalCost = $this->additionalCostRepository->updateAdditionalCost($request->all(), $departmentQuery, $id);
            return response()->json([
                'message' => 'Additional Cost successfully updated!',
                'data' => $additionalCost->load('formula'),
            ], 201);
        }else{
            return response()->json([
                'message' => 'Additional Cost not found!',
            ], 404);
        }
    }

    function assetDepreciation(Request $request, $id)
    {
        $validator = $this->validateRequest($request);
        $additionalCost = $this->getAdditionalCost($id);

        if (!$additionalCost) {
            return $this->buildResponse('Route not found.', 404);
        }

        $validationState = $this->checkDateValidation($validator, $additionalCost);

        if (!$validationState['status']) {
            return $this->buildResponse($validationState['message'], 422);
        }

        $calculationData = $this->calculateDepreciation($validator, $additionalCost);

        if ($additionalCost->depreciation_method === 'One Time') {
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
                'date.date_format' => 'Date format is invalid.',
            ]);
    }

    function getAdditionalCost($id)
    {
        return AdditionalCost::with('formula')->where('id', $id)->first();
    }

    function checkDateValidation($validator, $additionalCost): array
    {
        $custom_end_depreciation = $validator->validated()['date'];
        $end_depreciation = $additionalCost->formula->end_depreciation;
        $start_depreciation = $additionalCost->formula->start_depreciation;

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

    function calculateDepreciation($validator, $additionalCost): array
    {
        //Variable declaration
        $properties = $additionalCost->formula;
        $depreciation_method = $additionalCost->depreciation_method;
        $est_useful_life = $additionalCost->majorCategory->est_useful_life;
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
            'data' => $this->additionalCostRepository->transformSingleAdditionalCost($fixed_asset)
        ], 200);
    }

    public function sampleFixedAssetDownload()
    {
        //download file from storage/sample
        $path = storage_path('app/sample/fixed_asset.xlsx');
        return response()->download($path);
    }

}
