<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\MinorCategory\MinorCategoryRequest;
use App\Models\FixedAsset;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class MinorCategoryController extends Controller
{

    use ApiResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $majorCategoryStatus = $request->status ?? 'active';
        $isActiveStatus = ($majorCategoryStatus === 'deactivated') ? 0 : 1;


        $minorCategory = MinorCategory::withTrashed()->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPaginate();

        $minorCategory->transform(function ($minorCategory) {
            return [
                'id' => $minorCategory->id,
                'account_title' => [
                    'id' => $minorCategory->accountTitle->id ?? '-',
                    'sync_id' => $minorCategory->accountTitle->sync_id ?? '-',
                    'account_title_code' => $minorCategory->accountTitle->account_title_code ?? '-',
                    'account_title_name' => $minorCategory->accountTitle->account_title_name ?? '-',
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
            ];
        });

        return $minorCategory;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(MinorCategoryRequest $request)
    {
        // $division_id = $request->division_id;
        $account_title_sync_id = $request->account_title_sync_id;
        $major_cat_id = $request->major_category_id;
        $minor_cat_name = ucwords(strtolower($request->minor_category_name));
        // $minor_category_name_check = str_replace(' ', '', $minor_cat_name);


        $major_cat_id_check = MajorCategory::where('id', $major_cat_id)->exists();
        if (!$major_cat_id_check) {
            return $this->responseNotFound('Major Category Not Found');
        }

        $minorCategory = MinorCategory::withTrashed()->where('minor_category_name', $minor_cat_name)
            ->where('major_category_id', $major_cat_id)
            ->exists();
        if ($minorCategory) {
//            return response()->json(
//                [
//                    'message' => 'The given data was invalid.',
//                    'errors' => [
//                        'minor_category_name' => [
//                            'The minor category name has already been taken.'
//                        ]
//                    ]
//                ],
//                422
//            );
            return $this->responseUnprocessableEntity('The minor category name has already been taken.');
        }


        $create = MinorCategory::create([
            'account_title_sync_id' => $account_title_sync_id,
            'major_category_id' => $major_cat_id,
            'minor_category_name' => $minor_cat_name,
            'is_active' => 1
        ]);

//        return response()->json([
//            'message' => 'Successfully Created',
//            'data' => $create
//        ]);

        return $this->responseCreated('Successfully Created');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $MinorCategory = MinorCategory::query();
        if (!$MinorCategory->where('id', $id)->exists()) {
//            return response()->json(
//                [
//                    'error' => 'Minor Category Route Not Found'
//                ],
//                404
//            );
            return $this->responseNotFound('Minor Category Route Not Found');
        }
        $minorCategory = MinorCategory::with('majorCategory')->where('id', $id)->first();
        return response()->json([
            'data' => [
                'id' => $minorCategory->id,
                'account_title' => [
                    'id' => $minorCategory->accountTitle->id,
                    'sync_id' => $minorCategory->accountTitle->sync_id,
                    'account_title_code' => $minorCategory->accountTitle->account_title_code,
                    'account_title_name' => $minorCategory->accountTitle->account_title_name,
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

//        return $this->responseSuccess($minorCategory);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(MinorCategoryRequest $request, $id)
    {
        $minor_category_name = ucwords(strtolower($request->minor_category_name));
        $account_title_sync_id = $request->account_title_sync_id;
        // $minor_category_name_check = str_replace(' ', '', $minor_category_name);


        if (MinorCategory::where('id', $id)
            ->where(['minor_category_name' => $minor_category_name, 'account_title_sync_id' => $account_title_sync_id])
            ->exists()
        ) {
//            return response()->json(['message' => 'No Changes'], 200);

            return $this->responseSuccess('No Changes');
        }

        if (MinorCategory::where('id', $id)->exists()) {
            $update = MinorCategory::where('id', $id)
                ->update([
                    'account_title_sync_id' => $account_title_sync_id,
                    'minor_category_name' => $minor_category_name,
                ]);
//            return response()->json(['message' => 'Successfully Updated!'], 200);
            return $this->responseSuccess('Successfully Updated!');
        } else {
//            return response()->json(['error' => 'Minor Category Route Not Found'], 404);
            return $this->responseNotFound('Minor Category Route Not Found');
        }
    }


    public function archived(MinorCategoryRequest $request, $id)
    {

        $status = $request->status;
        $MinorCategory = MinorCategory::query();
        if (!$MinorCategory->withTrashed()->where('id', $id)->exists()) {
//            return response()->json(['error' => 'Minor Category Route Not Found'], 404);
            return $this->responseNotFound('Minor Category Route Not Found');
        }


        if ($status == false) {
            if (!MinorCategory::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
                return $this->responseSuccess('No Changes');
            } else {
                $checkFixedAsset = FixedAsset::where('minor_category_id', $id)->exists();
                if ($checkFixedAsset) {
//                    return response()->json(['error' => 'Unable to archived , Minor Category is still in use!'], 422);
                    return $this->responseUnprocessable('Unable to archived , Minor Category is still in use!');
                }
                $updateStatus = $MinorCategory->where('id', $id)->update(['is_active' => false]);
                $MinorCategory->where('id', $id)->delete();
//                return response()->json(['message' => 'Successfully Deactivated!'], 200);
                return $this->responseSuccess('Successfully Deactivated!');
            }
        }
        if ($status == true) {
            if (MinorCategory::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
                return $this->responseSuccess('No Changes');
            } else {
                $checkMajorCategory = MajorCategory::where('id', $MinorCategory->where('id', $id)->first()->major_category_id)->exists();
                if (!$checkMajorCategory) {
//                    return response()->json(['error' => 'Unable to Restore!, Major Category was Archived!'], 422);
                    return $this->responseUnprocessable('Unable to Restore!, Major Category was Archived!');
                }
                $restoreUser = $MinorCategory->withTrashed()->where('id', $id)->restore();
                $updateStatus = $MinorCategory->update(['is_active' => true]);
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


        $MinorCategory = MinorCategory::withTrashed()->with(['majorCategory' => function ($query) {
            $query->withTrashed();
        }])
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('minor_category_name', 'LIKE', "%{$search}%");
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->where('major_category_name', 'LIKE', "%{$search}%");
                });
                $query->orWhereHas('accountTitle', function ($query) use ($search) {
                    $query->where('account_title_name', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        $MinorCategory->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'account_title' => [
                    'id' => $item->accountTitle->id,
                    'sync_id' => $item->accountTitle->sync_id,
                    'account_title_code' => $item->accountTitle->account_title_code,
                    'account_title_name' => $item->accountTitle->account_title_name,
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
