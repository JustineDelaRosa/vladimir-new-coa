<?php

namespace App\Http\Controllers;

use App\Models\Division;
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(MajorCategoryRequest $request)
    {
        $division_id = $request->division_id;
        $major_category_name = strtoupper($request->major_category_name);
        // $major_category_name_check = str_replace(' ', '', $major_category_name);

        $majorCategory = MajorCategory::withTrashed()->where('major_category_name', $major_category_name)
            ->where('division_id', $division_id)
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


        $create = MajorCategory::create([
            'division_id' => $division_id,
            'major_category_name' => $major_category_name,
            'is_active' => true
        ]);
        return response()->json(['message' => 'Successfully Created!', 'data' => $create]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(MajorCategoryRequest $request, $id)
    {
        $MajorCategory = MajorCategory::query();
        if (!$MajorCategory->where('id', $id)->exists()) {
            return response()->json(['error' => 'Major Category Route Not Found'], 404);
        }

        return $MajorCategory->with('division')->where('id', $id)->first();

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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(MajorCategoryRequest $request, $id)
    {
        $division_id = $request->division_id;
        $major_category_name = strtoupper($request->major_category_name);
        $major_category_name_check = str_replace(' ', '', $major_category_name);


        // $major_category = MajorCategory::find($id);
        // if (!$major_category) {
        //     return response()->json(['error' => 'Major Category Route Not Found1'], 404);
        // }

        // $major_category = MajorCategory::where('id', $id)->where('division_id', $division_id)->exists();
        // if (!$major_category) {
        //     return response()->json(['error' => 'Major Category Route Not Found2'], 404);
        // }

        if (MajorCategory::where('id', $id)
            ->where(['major_category_name' => $major_category_name, 'division_id' => $division_id])
            ->exists()
        ) {
            return response()->json(['message' => 'No Changes'], 200);
        }
        $majorCategory = MajorCategory::withTrashed()
            ->where('major_category_name', $major_category_name)
            ->where('division_id', $division_id)
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
                'division_id' => $division_id,
                'major_category_name' => $major_category_name,
                // 'is_active' => true
            ]);
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
                $checkMajorCategory = MinorCategory::where('major_category_id', $id)->exists();
                if ($checkMajorCategory) {
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
                $checkDivision = Division::where('id', $MajorCategory->where('id', $id)->first()->division_id)->exists();
                if (!$checkDivision) {
                    return response()->json(['message' => 'Unable to Restore!, Division was Archived!'], 409);
                }
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

        $MajorCategory = MajorCategory::withTrashed()->with('division', function ($query) use ($status) {
            $query->where('is_active', $status);
            $query->withTrashed();
        })
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('major_category_name', 'LIKE', "%{$search}%");
                $query->orWhereHas('division', function ($query) use ($search) {
                    $query->where('division_name', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        //if division was trashed or not trashed


        $MajorCategory->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,

                'division' => [
                    'id' => $item->division->id ?? null,
                    'division_name' => $item->division->division_name ?? null,
                ],
                'major_category_name' => $item->major_category_name,
                'is_active' => $item->is_active,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'deleted_at' => $item->deleted_at,
            ];
        });


        return $MajorCategory;
    }
}
