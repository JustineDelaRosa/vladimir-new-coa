<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\CategoryListTagMinorCategory;
use App\Http\Requests\MinorCategory\MinorCategoryRequest;

class MinorCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $MinorCategory = MinorCategory::with('majorCategory')->get();
        return response()->json([
            'data' => $MinorCategory
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(MinorCategoryRequest $request)
    {
        // $division_id = $request->division_id;
        $major_cat_id = $request->major_category_id;
        $minor_cat_name = strtoupper($request->minor_category_name);
        // $minor_category_name_check = str_replace(' ', '', $minor_cat_name);




        $major_cat_id_check = MajorCategory::where('id', $major_cat_id)->exists();
        if (!$major_cat_id_check) {
            return response()->json([
                'error' => 'Major Category Not Found'
            ], 404);
        }

        $minorCategory = MinorCategory::withTrashed()->where('minor_category_name', $minor_cat_name)
            ->where('major_category_id', $major_cat_id)
            ->exists();
        if ($minorCategory) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'minor_category_name' => [
                            'The minor category name has already been taken.'
                        ]
                    ]
                ],
                422
            );
        }


        $create = MinorCategory::create([
            'major_category_id' => $major_cat_id,
            'minor_category_name' => $minor_cat_name,
            'is_active' => 1
        ]);

        return response()->json([
            'message' => 'Successfully Created',
            'data' => $create
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $MinorCategory = MinorCategory::query();
        if (!$MinorCategory->where('id', $id)->exists()) {
            return response()->json(
                [
                    'error' => 'Minor Category Route Not Found'
                ],
                404
            );
        }
        $minorCategory = MinorCategory::with('majorCategory.division')->where('id', $id)->first();
        return response()->json([
            'data' => [
                'id' => $minorCategory->id,
                'division' => [
                    'id' => $minorCategory->majorCategory->division->id,
                    'division_name' => $minorCategory->majorCategory->division->division_name,
                ],
                'major_category' => [
                    'id' => $minorCategory->majorCategory->id,
                    'major_category_name' => $minorCategory->majorCategory->major_category_name,

                ],
                'minor_category_name' => $minorCategory->minor_category_name,
                'is_active' => $minorCategory->is_active,
                'created_at' => $minorCategory->created_at,
                'updated_at' => $minorCategory->updated_at,
                'deleted_at' => $minorCategory->deleted_at
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(MinorCategoryRequest $request, $id)
    {
        $major_category_id = $request->major_category_id;
        $minor_category_name = strtoupper($request->minor_category_name);
        // $minor_category_name_check = str_replace(' ', '', $minor_category_name);

        // if (!MinorCategory::where('id', $id)->exists()) {
        //     return response()->json(['error' => 'Minor Category Route Not Found'], 404);
        // }
        // if (!MinorCategory::where('id', $id)->where('major_category_id', $major_category_id)->exists()) {
        //     return response()->json(['error' => 'Minor Category Route Not Found'], 404);
        // }

        if (MinorCategory::where('id', $id)
            ->where(['minor_category_name' => $minor_category_name, 'major_category_id' => $major_category_id])
            ->exists()
        ) {
            return response()->json(['message' => 'No Changes'], 200);
        }

        $minorCategory = MinorCategory::withTrashed()
            ->where('minor_category_name', $minor_category_name)
            ->where('major_category_id', $major_category_id)
            ->where('id', '!=', $id)
            ->exists();
        if ($minorCategory) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'minor_category_name' => [
                            'The minor category name has already been taken.'
                        ]
                    ]
                ],
                422
            );
        }

        if (MinorCategory::where('id', $id)->exists()) {
            $update = MinorCategory::where('id', $id)
                ->update([
                    'major_category_id' => $major_category_id,
                    'minor_category_name' => $minor_category_name,
                ]);
            return response()->json(['message' => 'Successfully Updated!'], 200);
        } else {
            return response()->json(['error' => 'Minor Category Route Not Found'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }


    public function archived(MinorCategoryRequest $request, $id)
    {

        $status = $request->status;
        $MinorCategory = MinorCategory::query();
        if (!$MinorCategory->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'Minor Category Route Not Found'], 404);
        }

        // if (CategoryListTagMinorCategory::where('minor_category_id', $id)->exists()) {
        //     if ($status == true) {
        //         return response()->json(['message' => 'No Changes'], 200);
        //     } else {
        //         return response()->json(['message' => 'Unable to Archived!'], 409);
        //     }
        // }

        $checkMajorCategory = MajorCategory::where('id', $MinorCategory->where('id', $id)->first()->major_category_id)->exists();
        if (!$checkMajorCategory) {
            return response()->json(['error' => 'Unable to Restore!, Major Category was Archived!'], 409);
        }

        if ($status == false) {
            if (!MinorCategory::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $updateStatus = $MinorCategory->where('id', $id)->update(['is_active' => false]);
                $MinorCategory->where('id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactived!'], 200);
            }
        }
        if ($status == true) {
            if (MinorCategory::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $restoreUser = $MinorCategory->withTrashed()->where('id', $id)->restore();
                $updateStatus = $MinorCategory->update(['is_active' => true]);
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


        $MinorCategory = MinorCategory::withTrashed()->with(['majorCategory' => function ($query) {
            $query->withTrashed()->with(['division' => function ($query) {
                $query->withTrashed();
            }]);
        }])
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('minor_category_name', 'LIKE', "%{$search}%");
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->where('major_category_name', 'LIKE', "%{$search}%");
                });
                $query->orWhereHas('majorCategory.division', function ($query) use ($search) {
                    $query->withTrashed()->where('division_name', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        $MinorCategory->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'division' => [
                    'id' => $item->majorCategory->division->id,
                    'division_name' => $item->majorCategory->division->division_name,
                ],
                'major_category' => [
                    'id' => $item->majorCategory->id,
                    'major_category_name' => $item->majorCategory->major_category_name,
                ],
                'minor_category_name' => $item->minor_category_name,
                'is_active' => $item->is_active,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'deleted_at' => $item->deleted_at
            ];
        });

        return $MinorCategory;


    }
}
