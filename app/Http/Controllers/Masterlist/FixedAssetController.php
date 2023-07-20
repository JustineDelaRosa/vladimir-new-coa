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
use App\Models\Division;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use App\Repositories\FixedAssetRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FixedAssetController extends Controller
{
    private $fixedAssetRepository, $vladimirTagGeneratorRepository;

    public function __construct()
    {
        $this->fixedAssetRepository = new FixedAssetRepository();
        $this->vladimirTagGeneratorRepository = new VladimirTagGeneratorRepository();
    }


    public function index()
    {
        $fixed_assets = FixedAsset::with('formula')->get();
        return response()->json([
            'message' => 'Fixed Assets retrieved successfully.',
            'data' => $fixed_assets
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
        if ($departmentQuery->division_id == null) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'division' => [
                            'The division is required.'
                        ]
                    ]
                ],
                422
            );
        }
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
            'data' => $this->fixedAssetRepository->transformFixedAsset($fixed_asset)
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
//        if( FixedAsset::where('id', $id)->first()) {
//            return response()->json([
//                'message' => 'No changes made.',
//                'data' => $request->all()
//            ], 200);
//        }
        $departmentQuery = Department::where('id', $request->department_id)->first();
        if ($departmentQuery->division_id == null) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'division' => [
                            'The division is required.'
                        ]
                    ]
                ],
                422
            );
        }

        $fixedAsset = FixedAsset::where('id', $id)->first();
        if ($fixedAsset) {
            $fixedAsset = $this->fixedAssetRepository->updateFixedAsset($request->all(), $departmentQuery);

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


    public function search(Request $request)
    {
        $search = $request->get('search');
        $limit = $request->get('limit');
        $page = $request->get('page');

        return $this->fixedAssetRepository->searchFixedAsset($search, $limit);
    }

    //todo change assetDescription
    function assetDepreciation(Request $request, $id)
    {
        //validation
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m',
        ],
            [
                'date.required' => 'Date is required.',
                'date.date_format' => 'Date format is invalid.',
            ]);

        $fixedAsset = FixedAsset::with('formula')->where('id', $id)->first();
        if (!$fixedAsset) {
            return response()->json([
                'message' => 'Route not found.'
            ], 404);
        }

        //Variable declaration
        $depreciation_method = $fixedAsset->depreciation_method;
        $est_useful_life = $fixedAsset->majorCategory->est_useful_life;
        $start_depreciation = $fixedAsset->formula->start_depreciation;
        $depreciable_basis = $fixedAsset->formula->depreciable_basis;
        $scrap_value = $fixedAsset->formula->scrap_value;
        $end_depreciation = $fixedAsset->formula->end_depreciation;
        $release_date = $fixedAsset->formula->release_date;
        $custom_end_depreciation = $validator->validated()['date'];

        if ($custom_end_depreciation == date('Y-m')) {
            if ($custom_end_depreciation > $end_depreciation) {
                $custom_end_depreciation = $end_depreciation;
            }
        } elseif (!$this->dateValidation($custom_end_depreciation, $start_depreciation, $fixedAsset->formula->end_depreciation)) {
            return response()->json([
                'message' => 'Date is invalid.'
            ], 422);
        }

        //calculation variables
        $custom_age = $this->getMonthsDepreciated($start_depreciation, $custom_end_depreciation);
        $monthly_depreciation = $this->getMonthlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life);
        $yearly_depreciation = $this->getYearlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life);
        $accumulated_cost = $this->getAccumulatedCost($monthly_depreciation, $custom_age);
        $remaining_book_value = $this->getRemainingBookValue($depreciable_basis, $accumulated_cost);


        if ($fixedAsset->depreciation_method == 'One Time') {
            $age = 0.083333333333333;
            $monthly_depreciation = $this->getMonthlyDepreciation($depreciable_basis, $scrap_value, $age);
            return response()->json([
                'message' => 'Depreciation retrieved successfully.',
                'data' => [
                    'depreciation_method' => $depreciation_method,
                    'depreciable_basis' => $depreciable_basis,
                    'start_depreciation' => $start_depreciation,
                    'end_depreciation' => $end_depreciation,
                    'depreciation' => $monthly_depreciation,
                    'depreciation_per_month' => 0,
                    'depreciation_per_year' => 0,
                    'accumulated_cost' => 0,
                    'remaining_book_value' => 0,

                ]
            ], 200);
        }
        return response()->json([
            'message' => 'Depreciation calculated successfully',
            'data' => [
                'depreciation_method' => $depreciation_method,
                'depreciable_basis' => $depreciable_basis,
                'est_useful_life' => $est_useful_life,
                'months_depreciated' => $custom_age,
                'scarp_value' => $scrap_value,
                'start_depreciation' => $start_depreciation,
                'end_depreciation' => $end_depreciation,
                'depreciation_per_month' => $monthly_depreciation,
                'depreciation_per_year' => $yearly_depreciation,
                'accumulated_cost' => $accumulated_cost,
                'remaining_book_value' => $remaining_book_value,
            ]
        ], 200);

    }

    public function getMonthsDepreciated($start_depreciation, $custom_end_depreciation)
    {
        $start_depreciation = Carbon::parse($start_depreciation);
        $custom_end_depreciation = Carbon::parse($custom_end_depreciation);
        return $start_depreciation->diffInMonths($custom_end_depreciation->addMonth(1));
    }

    private function getYearlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life): float
    {
        return round(($depreciable_basis - $scrap_value) / $est_useful_life, 2);
    }

    private function getMonthlyDepreciation($depreciable_basis, $scrap_value, $est_useful_life): float
    {
        $yearly = ($depreciable_basis - $scrap_value) / $est_useful_life;
        return round($yearly / 12, 2);
    }

    private function dateValidation($date, $start_depreciation, $end_depreciation): bool
    {
        $date = Carbon::parse($date);
        $start_depreciation = Carbon::parse($start_depreciation);
        $end_depreciation = Carbon::parse($end_depreciation);
        if ($date->between($start_depreciation, $end_depreciation)) {
            return true;
        } else {
            return false;
        }
    }

    private function getAccumulatedCost($monthly_depreciation, float $custom_age): float
    {
        $accumulated_cost = $monthly_depreciation * $custom_age;
        return round($accumulated_cost);
    }

    private function getRemainingBookValue($depreciable_basis, float $accumulated_cost): float
    {
        $remaining_book_value = $depreciable_basis - $accumulated_cost;
        return round($remaining_book_value);
    }

    public function getEndDepreciation($start_depreciation, $est_useful_life)
    {

        $start_depreciation = Carbon::parse($start_depreciation);
        return $start_depreciation->addYears(floor($est_useful_life))->addMonths(floor(($est_useful_life - floor($est_useful_life)) * 12))->subMonth(1)->format('Y-m');

    }

    public function getStartDepreciation($release_date)
    {
        $release_date = Carbon::parse($release_date);
        return $release_date->addMonth(1)->format('Y-m');
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
            'data' => $this->fixedAssetRepository->transformFixedAsset($fixed_asset)
        ], 200);
    }

    public function sampleFixedAssetDownload()
    {
        //download file from storage/sample
        $path = storage_path('app/sample/fixed_asset.xlsx');
        return response()->download($path);
    }

}
