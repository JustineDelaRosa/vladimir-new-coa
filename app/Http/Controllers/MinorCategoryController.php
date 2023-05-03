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
        $minor_cat_name = ucwords(strtolower($request->minor_category_name));



        $major_cat_id_check = MajorCategory::where('id', $major_cat_id)->exists();
        if (!$major_cat_id_check) {
            return response()->json([
                'error' => 'Major Category Not Found'
            ], 404);
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
        return $MinorCategory->where('id', $id)->first();
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
        $minor_category_name = ucwords(strtolower($request->minor_category_name));
        $minor_category_name_check = str_replace(' ', '', $minor_category_name);

        if (!MinorCategory::where('id', $id)->exists()) {
            return response()->json(['error' => 'Minor Category Route Not Found'], 404);
        }
        if (!MinorCategory::where('id', $id)->where('major_category_id', $major_category_id)->exists()) {
            return response()->json(['error' => 'Minor Category Route Not Found'], 404);
        }

        if (MinorCategory::where('id', $id)
            ->where(['minor_category_name' => $minor_category_name, 'major_category_id' => $major_category_id])
            ->exists()
        ) {
            return response()->json(['message' => 'No Changes'], 200);
        }
        $update = MinorCategory::where('id', $id)
            ->update([
                'minor_category_name' => $minor_category_name,
            ]);
        return response()->json(['message' => 'Successfully Updated!'], 200);
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
            return response()->json(['error' => 'Unable to Archived!, Major Category was Archived!'], 409);
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


        $MinorCategory = MinorCategory::with(['majorCategory.division'])
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('minor_category_name', 'LIKE', "%{$search}%");
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->where('major_category_name', 'LIKE', "%{$search}%");
                });
                $query->orWhereHas('majorCategory.division', function ($query) use ($search) {
                    $query->where('division_name', 'LIKE', "%{$search}%");
                });
            })
            ->orderby('created_at', 'DESC')
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


        // $MinorCategory = MinorCategory::withTrashed()
        //     ->where(function ($query) use ($status) {
        //         $query->where('is_active', $status);
        //     })
        //     ->where(function ($query) use ($search) {
        //         $query->where('minor_category_name', 'LIKE', "%{$search}%");
        //     })
        //     ->orderby('created_at', 'DESC')
        //     ->paginate($limit);
        // return $MinorCategory;
    }
}
