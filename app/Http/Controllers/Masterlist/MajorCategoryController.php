<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\MajorCategory\MajorCategoryRequest;
use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Repositories\CalculationRepository;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class MajorCategoryController extends Controller
{
    use ApiResponse;

    private $calculationRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $majorCategoryStatus = $request->status ?? 'active';
        $isActiveStatus = ($majorCategoryStatus === 'deactivated') ? 0 : 1;

        $majorCategory = MajorCategory::withTrashed()->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPaginate();

        $majorCategory->transform(function ($majorCategory) {
            return [
                'id' => $majorCategory->id,
                'major_category_name' => $majorCategory->major_category_name,
                'est_useful_life' => $majorCategory->est_useful_life,
                'minor_categories' => $majorCategory->minorCategory->map(function ($minorCategory) {
                    return [
                        'id' => $minorCategory->id,
                        'initial_debit' => [
                            'id' => $minorCategory->accountingEntries->initialDebit->id ?? '-',
                            'sync_id' => $minorCategory->accountingEntries->initialDebit->id ?? '-',
                            'account_title_code' => $minorCategory->accountingEntries->initialDebit->account_title_code ?? '-',
                            'account_title_name' => $minorCategory->accountingEntries->initialDebit->account_title_name ?? '-',
                            'depreciation_debit' => $minorCategory->initialDebit->depreciationDebit ?? '-',
                        ],
                        'initial_credit' => [
                            'id' => $minorCategory->accountingEntries->initialCredit->id ?? '-',
                            'sync_id' => $minorCategory->accountingEntries->initialCredit->id ?? '-',
                            'account_title_code' => $minorCategory->accountingEntries->initialCredit->account_title_code ?? '-',
                            'account_title_name' => $minorCategory->accountingEntries->initialCredit->account_title_name ?? '-',
                        ],
                        'depreciation_debit' => [
                            'id' => $minorCategory->accountingEntries->depreciationDebit->id ?? '-',
                            'sync_id' => $minorCategory->accountingEntries->depreciationDebit->sync_id ?? '-',
                            'account_title_code' => $minorCategory->accountingEntries->depreciationDebit->account_title_code ?? '-',
                            'account_title_name' => $minorCategory->accountingEntries->depreciationDebit->account_title_name ?? '-',
                        ],
                        'depreciation_credit' => [
                            'id' => $minorCategory->accountingEntries->depreciationCredit->id ?? '-',
                            'sync_id' => $minorCategory->accountingEntries->depreciationCredit->sync_id ?? '-',
                            'account_title_code' => $minorCategory->accountingEntries->depreciationCredit->credit_code ?? '-',
                            'account_title_name' => $minorCategory->accountingEntries->depreciationCredit->credit_name ?? '-',
                        ],
                        'minor_category_name' => $minorCategory->minor_category_name,
                        'is_active' => $minorCategory->is_active,
                        'created_at' => $minorCategory->created_at,
                        'updated_at' => $minorCategory->updated_at,
                        'deleted_at' => $minorCategory->deleted_at
                    ];
                }),
                'is_active' => $majorCategory->is_active,
                'created_at' => $majorCategory->created_at,
                'updated_at' => $majorCategory->updated_at,
                'deleted_at' => $majorCategory->deleted_at,
                'minor_category' => $majorCategory->minorCategory
            ];
        });

        return $majorCategory;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return float|\Illuminate\Http\JsonResponse|int
     */
    public function store(MajorCategoryRequest $request)
    {
        $major_category_name = ucwords(strtolower($request->major_category_name));
        $est_useful_life = $request->est_useful_life;

//        $majorCategory = MajorCategory::withTrashed()->where('major_category_name', $major_category_name)
//            ->exists();
//        if ($majorCategory) {
////            return response()->json(
////                [
////                    'message' => 'The given data was invalid.',
////                    'errors' => [
////                        'major_category_name' => [
////                            'The major category name has already been taken.'
////                        ]
////                    ]
////                ],
////                422
////            );
//
//            return $this->responseUnprocessable('The major category name has already been taken.');
//        }


        $majorCategory = MajorCategory::create([
            'major_category_name' => $major_category_name,
            'est_useful_life' => $est_useful_life,
            'is_active' => true
        ]);


//        return response()->json(['message' => 'Successfully Created!', 'data' => $majorCategory], 201);
        return $this->responseCreated('Successfully Created!', $majorCategory);

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|\Illuminate\Http\JsonResponse|object
     */
    public function show(MajorCategoryRequest $request, $id)
    {
        $MajorCategory = MajorCategory::query();
        if (!$MajorCategory->where('id', $id)->exists()) {
//            return response()->json(['error' => 'Major Category Route Not Found'], 404);
            return $this->responseNotFound('Major Category Route Not Found');
        }

        return $MajorCategory->with('minorCategory')->where('id', $id)->first();

        // $MajorCategory =  MajorCategory::with('division')->where('id', $id)->first();
        // return response()->json([
        //     'data' => [
        //         'id' => $MajorCategory->id,
        //         'division' => [
        //             'id' => $MajorCategory->division->id,
        //             'division_name' => $MajorCategory->division->division_name
        //         ],
        //         'major_category_name' => $MajorCategory->major_category_name,
        //         'is_active' => $MajorCategory->is_active,
        //         'created_at' => $MajorCategory->created_at,
        //         'updated_at' => $MajorCategory->updated_at,
        //         'deleted_at' => $MajorCategory->deleted_at
        //     ]
        // ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(MajorCategoryRequest $request, $id)
    {
        $major_category_name = ucwords(strtolower($request->major_category_name));
        $est_useful_life = $request->est_useful_life;


        // $major_category = MajorCategory::find($id);
        // if (!$major_category) {
        //     return response()->json(['error' => 'Major Category Route Not Found1'], 404);
        // }

        // $major_category = MajorCategory::where('id', $id)->where('division_id', $division_id)->exists();
        // if (!$major_category) {
        //     return response()->json(['error' => 'Major Category Route Not Found2'], 404);
        // }

        $majorCategory = MajorCategory::withTrashed()
            ->find($id);

        if (!$majorCategory) {
//            return response()->json(['error' => 'Major Category Route Not Found'], 404);
            return $this->responseNotFound('Major Category Route Not Found');
        }

        if ($majorCategory->major_category_name === $major_category_name && $majorCategory->est_useful_life === $est_useful_life) {
//            return response()->json(['message' => 'No Changes'], 200);
            return $this->responseSuccess('No Changes');
        }

        if (MajorCategory::where(['major_category_name' => $major_category_name])
            ->where('id', '!=', $id)
            ->withTrashed()
            ->first()
        ) {
//            return response()->json(
//                [
//                    'message' => 'The given data was invalid.',
//                    'errors' => [
//                        'major_category_name' => [
//                            'The major category name has already been taken.'
//                        ]
//                    ]
//                ],
//                422
//            );
            return $this->responseUnprocessable('The major category name has already been taken.');
        }

        $majorCategory->major_category_name = $major_category_name;
        $majorCategory->est_useful_life = $est_useful_life;

//        $fixedAsset = FixedAsset::withTrashed()->where('major_category_id', $id)->get();
//        $additionalCost = AdditionalCost::withTrashed()->where('major_category_id', $id)->get();
//
//        $this->applyEndDepreciation($fixedAsset, $majorCategory);
//        $this->applyEndDepreciation($additionalCost, $majorCategory);
//
//        $majorCategory->save();

        $fixedAsset = FixedAsset::withTrashed()
            ->where('major_category_id', $id)
            ->with('depreciationStatus')
            ->get();

        $additionalCost = AdditionalCost::withTrashed()
            ->where('major_category_id', $id)
            ->with('depreciationStatus')
            ->get();

        foreach ($fixedAsset as $item) {
            if ($item->depreciationStatus->depreciation_status_name != 'For Depreciation') {
                $this->applyEndDepreciation($item, $majorCategory);
            }
        }

        foreach ($additionalCost as $item) {
            if ($item->depreciationStatus->depreciation_status_name != 'For Depreciation') {
                $this->applyEndDepreciation($item, $majorCategory);
            }
        }

        $majorCategory->save();

//        return response()->json(['message' => 'Successfully Updated!'], 200);
        return $this->responseSuccess('Successfully Updated!');
    }


    public function archived(MajorCategoryRequest $request, $id)
    {

        $status = $request->status;
        $MajorCategory = MajorCategory::query();
        if (!$MajorCategory->withTrashed()->where('id', $id)->exists()) {
//            return response()->json(['error' => 'Major Category Route Not Found'], 404);
            return $this->responseNotFound('Major Category Route Not Found');
        }

        // if (MajorCategory::where('id', $id)->exists()) {
        //     if ($status == true) {
        //         return response()->json(['message' => 'No Changes'], 200);
        //     } else {
        //         return response()->json(['message' => 'Unable to Archived!'], 409);
        //     }
        // }


        if ($status == false) {
            if (!MajorCategory::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
                return $this->responseSuccess('No Changes');
            } else {
                $checkMinorCategory = MinorCategory::where('major_category_id', $id)->exists();
                if ($checkMinorCategory) {
//                    return response()->json(['message' => 'Unable to Archived!, Archived Minor Category First'], 409);
                    return $this->responseUnprocessable('Unable to Archived!, Archived Minor Category First');
                }
                if (MajorCategory::where('id', $id)->exists()) {
                    $updateStatus = $MajorCategory->where('id', $id)->update(['is_active' => false]);
                    $MajorCategory->where('id', $id)->delete();
//                    return response()->json(['message' => 'Successfully Deactived!'], 200);
                    return $this->responseSuccess('Successfully Deactivated!');
                }
            }
        }
        if ($status == true) {
            if (MajorCategory::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
                return $this->responseSuccess('No Changes');
            } else {
                $restoreUser = $MajorCategory->withTrashed()->where('id', $id)->restore();
                $updateStatus = $MajorCategory->update(['is_active' => true]);
//                return response()->json(['message' => 'Successfully Activated!'], 200);
                return $this->responseSuccess('Successfully Activated!');
            }
        }
    }

    public function search(Request $request)
    {
        $search = $request->query('search');
        $limit = $request->query('limit');
        $page = $request->get('page');
        $status = $request->query('status');
        if ($status == NULL) {
            $status = 1;
        }
        if ($status == "active") {
            $status = 1;
        }
        if ($status == "deactivated") {
            $status = 0;
        }
        if ($status != "active" || $status != "deactivated") {
            $status = 1;
        }

        $MajorCategory = MajorCategory::withTrashed()
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('major_category_name', 'LIKE', "%{$search}%")
                    ->orWhere('est_useful_life', 'LIKE', "%{$search}%");
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        //if division was trashed or not trashed


        $MajorCategory->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'major_category_name' => $item->major_category_name,
                'est_useful_life' => $item->est_useful_life,
                'is_active' => $item->is_active,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'deleted_at' => $item->deleted_at,
            ];
        });


        return $MajorCategory;
    }


    private function applyEndDepreciation($assets, $majorCategory)
    {
        foreach ($assets as $asset) {
            $formula = $asset->formula()->withTrashed()->first();
            $startDepreciation = $formula->start_depreciation;
            $depreciationMethod = $formula->depreciation_method;
            $formula->update([
                'end_depreciation' => $this->calculationRepository->getEndDepreciation($startDepreciation, $majorCategory->est_useful_life, $depreciationMethod)
            ]);
        }
    }
}
