<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdditionalCost\AdditionalCostRequest;
use App\Http\Requests\AdditionalCost\AdditionalCostSyncRequest;
use App\Imports\AdditionalCostImport;
use App\Imports\MasterlistImport;
use App\Models\AccountingEntries;
use App\Models\AccountTitle;
use App\Models\AdditionalCost;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\ElixirAC;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\Status\AssetStatus;
use App\Models\Status\CycleCountStatus;
use App\Models\Status\DepreciationStatus;
use App\Models\Status\MovementStatus;
use App\Models\TypeOfRequest;
use App\Models\UnitOfMeasure;
use App\Repositories\AdditionalCostRepository;
use App\Repositories\CalculationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class AdditionalCostController extends Controller
{
    protected $additionalCostRepository, $calculationRepository;

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


    public function store(AdditionalCostRequest $request)
    {
//        return $request->all();
//        $departmentQuery = Department::where('id', $request->department_id)->first();
        $businessUnitQuery = BusinessUnit::where('id', $request->business_unit_id)->first();
        $additionalCost = $this->additionalCostRepository->storeAdditionalCost($request->all(), $businessUnitQuery);

        return response()->json([
            'message' => 'Additional Cost successfully created!',
            'data' => $additionalCost,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $additional_cost = AdditionalCost::withTrashed()->with([
            'formula' => function ($query) {
                $query->withTrashed();
            },
            'fixedAsset' => function ($query) {
                $query->withTrashed();
            },
        ])->where('id', $id)->first();

        if (!$additional_cost) {
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
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AdditionalCostRequest $request, $id)
    {
        $request->validated();
//        $departmentQuery = Department::where('id', $request->department_id)->first();
        $additionalCost = AdditionalCost::where('id', $id)->first();
        $businessUnitQuery = BusinessUnit::where('id', $request->business_unit_id)->first();

        if ($additionalCost) {
            $additionalCost = $this->additionalCostRepository->updateAdditionalCost($request->all(), $businessUnitQuery, $id);
            return response()->json([
                'message' => 'Additional Cost successfully updated!',
                'data' => $additionalCost->load('formula'),
            ], 201);
        } else {
            return response()->json([
                'message' => 'Additional Cost not found!',
            ], 404);
        }
    }


    public function archived(AdditionalCostRequest $request, $id)
    {

        $status = $request->status;
        $remarks = ucwords($request->remarks);
        $additionalCost = AdditionalCost::query();
        $formula = Formula::query();
        if (!$additionalCost->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'Additional Cost Route Not Found'], 404);
        }

        if ($status == false) {
            if (!AdditionalCost::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {

                $depreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
                $fixedAssetExists = AdditionalCost::where('id', $id)->where('depreciation_status_id', $depreciationStatusId)->first();

                if ($fixedAssetExists) {
                    return response()->json(['errors' => 'Unable to Archive!, Depreciation is Running!'], 422);
                }

                $additionalCost->where('id', $id)->update(['remarks' => $remarks, 'is_active' => false]);
                Formula::where('id', AdditionalCost::where('id', $id)->first()->formula_id)->delete();
                $additionalCost->where('id', $id)->delete();
//                $formula->where('additional_cost_id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactivated!'], 200);
            }
        }
        if ($status == true) {
            if (AdditionalCost::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $checkMinorCategory = MinorCategory::where('id', $additionalCost->where('id', $id)->first()->minor_category_id)->exists();
                if (!$checkMinorCategory) {
                    return response()->json(['errors' => 'Unable to Restore!, Minor Category was Archived!'], 422);
                }

                $checkTypeOfRequest = TypeOfRequest::where('id', $additionalCost->where('id', $id)->first()->type_of_request_id)->exists();
                if (!$checkTypeOfRequest) {
                    return response()->json(['errors' => 'Unable to Restore!, Type of Request was Archived!'], 422);
                }

                $additionalCost->withTrashed()->where('id', $id)->restore();
                $additionalCost->update(['is_active' => true, 'remarks' => null]);
//                $formula->where('additional_cost_id', $id)->restore();
                Formula::where('id', AdditionalCost::where('id', $id)->first()->formula_id)->restore();
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
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
//        $depreciation_method = $additionalCost->depreciation_method;
//        $est_useful_life = $additionalCost->majorCategory->est_useful_life;
        $custom_end_depreciation = $validator->validated()['date'];

        //FOR INFORMATION
        $depreciation_method = $properties->depreciation_method ?? null;
        $est_useful_life = $additionalCost->majorCategory->est_useful_life ?? 0;
        $acquisition_date = $properties->acquisition_date ?? null;
        $acquisition_cost = $properties->acquisition_cost ?? null;
        $scrap_value = $properties->scrap_value ?? null;


        //calculation variables
        $custom_age = $this->calculationRepository->getMonthDifference($properties->start_depreciation, $custom_end_depreciation);
        $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($properties->acquisition_cost, $properties->scrap_value, $est_useful_life) ?? 0;
        $yearly_depreciation = $this->calculationRepository->getYearlyDepreciation($properties->acquisition_cost, $properties->scrap_value, $est_useful_life) ?? 0;
        $accumulated_cost = $this->calculationRepository->getAccumulatedCost($monthly_depreciation, $custom_age, $properties->depreciable_basis) ?? 0;
        $remaining_book_value = $this->calculationRepository->getRemainingBookValue($properties->acquisition_cost, $accumulated_cost) ?? 0;

        if ($depreciation_method === 'One Time') {
            $age = 0.08333333333333;
            $monthly_depreciation = $this->calculationRepository->getMonthlyDepreciation($properties->acquisition_cost, $properties->scrap_value, $age) ?? 0;
        }

        $isDepreciated = $additionalCost->depreciation_method !== null;

        return [
            'onetime' => [
                'depreciation_method' => $isDepreciated ? $depreciation_method : '-',
                'depreciable_basis' => $isDepreciated ? $properties->depreciable_basis : 0,
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
                    'id' => $additionalCost->accountTitle->initialDebit->id ?? '-',
                    'account_title_code' => $additionalCost->accountTitle->initialDebit->account_title_code ?? '-',
                    'account_title_name' => $additionalCost->accountTitle->initialDebit->account_title_name ?? '-',
                ],
                'initial_credit' => [
                    'id' => $additionalCost->accountTitle->initialCredit->id ?? '-',
                    'account_title_code' => $additionalCost->accountTitle->initialCredit->account_title_code ?? '-',
                    'account_title_name' => $additionalCost->accountTitle->initialCredit->account_title_name ?? '-',
                ],
                'depreciation_debit' => [
                    'id' => $additionalCost->accountTitle->depreciationDebit->id ?? '-',
                    'account_title_code' => $additionalCost->accountTitle->depreciationDebit->account_title_code ?? '-',
                    'account_title_name' => $additionalCost->accountTitle->depreciationDebit->account_title_name ?? '-',
                ],
                'depreciation_credit' => [
                    'id' => $additionalCost->accountTitle->depreciationCredit->id ?? '-',
                    'account_title_code' => $additionalCost->accountTitle->depreciationCredit->account_title_code ?? '-',
                    'account_title_name' => $additionalCost->accountTitle->depreciationCredit->account_title_name ?? '-',
                ],
            ],
            'default' => [
                'depreciation_method' => $isDepreciated ? $depreciation_method : '-',
                'depreciable_basis' => $isDepreciated ? $properties->depreciable_basis : 0,
                'est_useful_life' => $est_useful_life ?? '-',
                'months_depreciated' => $isDepreciated ? $custom_age : 0,
                'scrap_value' => $scrap_value ?? '-',
                'start_depreciation' => $isDepreciated ? $properties->start_depreciation : '-',
                'end_depreciation' => $isDepreciated ? $properties->end_depreciation : '-',
                'depreciation_per_month' => $isDepreciated ? $monthly_depreciation : 0,
                'depreciation_per_year' => $isDepreciated ? $yearly_depreciation : 0,
                'accumulated_cost' => $isDepreciated ? $accumulated_cost : 0,
                'remaining_book_value' => $isDepreciated ? $remaining_book_value : 0,
                'acquisition_date' => $acquisition_date ?? '-',
                'acquisition_cost' => $acquisition_cost ?? '-',
                'initial_debit' => [
                    'id' => $additionalCost->accountTitle->initialDebit->id ?? '-',
                    'account_title_code' => $additionalCost->accountTitle->initialDebit->account_title_code ?? '-',
                    'account_title_name' => $additionalCost->accountTitle->initialDebit->account_title_name ?? '-',
                ],
                'initial_credit' => [
                    'id' => $additionalCost->accountTitle->initialCredit->id ?? '-',
                    'account_title_code' => $additionalCost->accountTitle->initialCredit->account_title_code ?? '-',
                    'account_title_name' => $additionalCost->accountTitle->initialCredit->account_title_name ?? '-',
                ],
                'depreciation_debit' => [
                    'id' => $additionalCost->accountTitle->depreciationDebit->id ?? '-',
                    'account_title_code' => $additionalCost->accountTitle->depreciationDebit->account_title_code ?? '-',
                    'account_title_name' => $additionalCost->accountTitle->depreciationDebit->account_title_name ?? '-',
                ],
                'depreciation_credit' => [
                    'id' => $additionalCost->accountTitle->depreciationCredit->id ?? '-',
                    'account_title_code' => $additionalCost->accountTitle->depreciationCredit->account_title_code ?? '-',
                    'account_title_name' => $additionalCost->accountTitle->depreciationCredit->account_title_name ?? '-',
                ],
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


    public function additionalCostImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlx,xls,pdf,xlsx'
        ]);

        $file = $request->file('file');

        Excel::import(new AdditionalCostImport, $file);

        //put into an array the data from the Excel file
        $data = Excel::toArray(new AdditionalCostImport, $file);
        return response()->json(
            [
                'message' => 'Additional Cost imported successfully.',
            ],
            200
        );
    }

    public function sampleAdditionalCostDownload(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = storage_path('app/sample/additionalCost.xlsx');
        return response()->download($path);
    }
    public function tagToAsset(Request $request){
//        $addCostItems = $request->input('assetTag');
//        'add_cost_sequence' => $this->getAddCostSequence($request['fixed_asset_id']) ?? '-',

        DB::beginTransaction();
        try {
            $additionalCosts = $request->assetTag;
            foreach ($additionalCosts as $additionalCost) {
                $fixedAssetId = FixedAsset::where('vladimir_tag_number', $additionalCost['assetTag'])->first()->id;
                $businessUnitQuery = BusinessUnit::where('id', $additionalCost['businessUnitId'])->first();
                $majorCategory = MajorCategory::whereRaw('LOWER(major_category_name) = ?', [strtolower($additionalCost['majorCategoryName'])])->first();
                $majorCategoryId = $majorCategory->id;
                $minorCategory = MinorCategory::whereRaw('LOWER(minor_category_name) = ?', [strtolower($additionalCost['minorCategoryName'])])->first();
                $minorCategoryId = $minorCategory->id;
                $depreciationMethod = $additionalCost['unitPrice'] < 10000 ? 'One Time' : 'STL';
                $depreciationStatusId = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
                $typeOfRequestId = TypeOfRequest::where('type_of_request_name', 'Asset')->first()->id;
                $assetStatus = AssetStatus::where('asset_status_name', 'Good')->first()->id;
                $cycleCountStatus = CycleCountStatus::where('cycle_count_status_name', 'On Site')->first()->id;
                $movementStatus = MovementStatus::where('movement_status_name', 'New Item')->first()->id;
                $uomId = UnitOfMeasure::whereRaw('LOWER(uom_name) = ?', [strtolower($additionalCost['uom'])])->first()->id;


                $additionalCost['type_of_request_id'] = $typeOfRequestId;
                $additionalCost['uom_id'] = $uomId;
                $additionalCost['fixed_asset_id'] = $fixedAssetId;
                $additionalCost['major_category_id'] = $majorCategoryId;
                $additionalCost['asset_status_id'] = $assetStatus;
                $additionalCost['cycle_count_status_id'] = $cycleCountStatus;
                $additionalCost['movement_status_id'] = $movementStatus;
                $additionalCost['minor_category_id'] = $minorCategoryId;
                $additionalCost['asset_description'] = $additionalCost['itemDescription'];
                $additionalCost['asset_specification'] = $additionalCost['itemDescription'];
                $additionalCost['accountability'] = 'Common';
                $additionalCost['quantity'] = $additionalCost['servedQuantity'];
                $additionalCost['depreciation_method'] = $depreciationMethod;
                $additionalCost['depreciation_status_id'] = $depreciationStatusId;
                $additionalCost['acquisition_date'] = date('Y-m-d', strtotime($additionalCost['acquisitionDate']));
                $additionalCost['acquisition_cost'] = $additionalCost['unitPrice'];
                $additionalCost['depreciable_basis'] = $additionalCost['unitPrice'];
                $additionalCost['months_depreciated'] = $this->calculationRepository->getMonthDifference($additionalCost['acquisitionDate'], date('Y-m-d'));

                $additionalCost['company_id'] = $additionalCost['companyId'];
                $additionalCost['business_unit_id'] = $additionalCost['businessUnitId'];
                $additionalCost['department_id'] = $additionalCost['departmentId'];
                $additionalCost['unit_id'] = $additionalCost['unitId'];
                $additionalCost['subunit_id'] = $additionalCost['subUnitId'];
                $additionalCost['location_id'] = $additionalCost['locationId'];

                $additionalCost = $this->additionalCostRepository->storeAdditionalCost($additionalCost, $businessUnitQuery);
            }
            DB::commit();
            return $this->responseSuccess('Successfully Tagged to Asset!');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
        }
    }

    public function syncData(AdditionalCostSyncRequest $request)
    {

//        return $additionalCosts = $request->assetTag;
        DB::beginTransaction();
        try {
            $test = [];
            $additionalCosts = $request->assetTag;
            foreach ($additionalCosts as $additionalCost) {
                ElixirAC::create([
                    'po_number' => $additionalCost['poNumber'],
                    'pr_number' => $additionalCost['prNumber'],
                    'mir_id' => $additionalCost['mirId'],
                    'warehouse_id' => $additionalCost['wareHouseId'],
                    'acquisition_date' => $additionalCost['acquisitionDate'],
                    'customer_code' => $additionalCost['customerCode'],
                    'customer_name' => $additionalCost['customerName'],
                    'item_code' => $additionalCost['itemCode'],
                    'item_description' => $additionalCost['itemDescription'],
                    'uom' => $additionalCost['uom'],
                    'served_quantity' => $additionalCost['servedQuantity'],
                    'asset_tag' => $additionalCost['assetTag'],
                    'approved_date' => $additionalCost['approveDate'],
                    'released_date' => $additionalCost['releasedDate'],
                    'unit_price' => $additionalCost['unitPrice'],
                    'company_id' => $additionalCost['companyId'],
                    'business_unit_id' => $additionalCost['businessUnitId'],
                    'department_id' => $additionalCost['departmentId'],
                    'unit_id' => $additionalCost['unitId'],
                    'sub_unit_id' => $additionalCost['subUnitId'],
                    'location_id' => $additionalCost['locationId'],
                    'major_category_name' => $additionalCost['majorCategoryName'],
                    'minor_category_name' => $additionalCost['minorCategoryName'],

                ]);
            }
            DB::commit();
            return $this->responseSuccess('Successfully Synced!');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable($e->getMessage());
        }
    }
}
