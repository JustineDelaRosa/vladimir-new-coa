<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Http\Requests\MajorCategory\MajorCategoryRequest;

class MajorCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $major_category = MajorCategory::with('minorCategory')->get();
        return response()->json([
            'data' => $major_category
        ], 200);
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

            $majorCategory = MajorCategory::withTrashed()->where('major_category_name', $major_category_name)
                ->exists();
            if ($majorCategory) {
                return response()->json(
                    [
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'major_category_name' => [
                                'The major category name has already been taken.'
                            ]
                        ]
                    ],
                    422
                );
            }


            $majorCategory = MajorCategory::create([
                'major_category_name' => $major_category_name,
                'est_useful_life'=> $est_useful_life,
                'is_active' => true
            ]);


        return response()->json(['message' => 'Successfully Created!', 'data' => $majorCategory], 201);

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
            return response()->json(['error' => 'Major Category Route Not Found'], 404);
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

        if (MajorCategory::where('id', $id)
            ->where(['major_category_name' => $major_category_name, 'est_useful_life'=> $est_useful_life])
            ->exists()
        ) {
            return response()->json(['message' => 'No Changes'], 200);
        }
        $majorCategory = MajorCategory::withTrashed()
            ->where('major_category_name', $major_category_name)
            ->where('id', '!=', $id)
            ->exists();
        if ($majorCategory) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'major_category_name' => [
                            'The major category name has already been taken.'
                        ]
                    ]
                ],
                422
            );
        }

        if (MajorCategory::where('id', $id)->exists()) {
            $update = MajorCategory::where('id', $id)->update([
                'major_category_name' => $major_category_name,
                'est_useful_life'=> $est_useful_life,
                // 'is_active' => true
            ]);

            $fixedAsset = FixedAsset::with('formula')->where('major_category_id', $id);
            foreach ($fixedAsset->get() as $fixedAsset) {
                if ($fixedAsset) {
                    // Use the fill method to update the attributes without saving
                    $fixedAsset->fill(['est_useful_life' => $est_useful_life]);
                    // Use the save method to save both the model and its relation in one query
                    $fixedAsset->push();
                    // Use the formula attribute to access the related model
                    $start_depreciation = $fixedAsset->formula->start_depreciation;
                    $fixedAsset->formula->est_useful_life = $est_useful_life;
                    // Use the end_depreciation attribute to calculate the value on the fly
                    $fixedAsset->formula->end_depreciation = (new FixedAssetController())->getEndDepreciation($start_depreciation, $fixedAsset->est_useful_life);
                    $fixedAsset->formula->save();
                }
            }
            return response()->json(['message' => 'Successfully Updated!'], 200);
        } else {
            return response()->json(['error' => 'Major Category Route Not Found'], 404);
        }


        // $update = MajorCategory::where('id', $id)->update([
        //     'division_id' => $division_id,
        //     'major_category_name' => $major_category_name,
        //     'is_active' => true
        // ]);
        // // return $major_category_name;
        // return response()->json(['message' => 'Successfully Updated!'], 200);
    }


    public function archived(MajorCategoryRequest $request, $id)
    {

        $status = $request->status;
        $MajorCategory = MajorCategory::query();
        if (!$MajorCategory->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'Major Category Route Not Found'], 404);
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
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $checkMinorCategory = MinorCategory::where('major_category_id', $id)->exists();
                if ($checkMinorCategory) {
                    return response()->json(['message' => 'Unable to Archived!, Archived Minor Category First'], 409);
                }
                if (MajorCategory::where('id', $id)->exists()) {
                    $updateStatus = $MajorCategory->where('id', $id)->update(['is_active' => false]);
                    $MajorCategory->where('id', $id)->delete();
                    return response()->json(['message' => 'Successfully Deactived!'], 200);
                }
            }
        }
        if ($status == true) {
            if (MajorCategory::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $restoreUser = $MajorCategory->withTrashed()->where('id', $id)->restore();
                $updateStatus = $MajorCategory->update(['is_active' => true]);
                return response()->json(['message' => 'Successfully Activated!'], 200);
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
}
