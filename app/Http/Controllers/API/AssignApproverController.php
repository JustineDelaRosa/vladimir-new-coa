<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignApprover\AssignApproverRequest;
use App\Models\ApproverLayer;
use App\Models\User;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AssignApproverController extends Controller
{

    use ApiResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $limit = $request->input('limit', null);

        $userApproverQuery = ApproverLayer::where(function ($query) use ($search, $limit) {
            $query->whereHas('requester', function ($query) use ($search) {
                $query->where('username', 'like', "%$search%")
                    ->orWhere('employee_id', 'like', "%$search%")
                    ->orWhere('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%");

            });
//                ->orWhereHas('approver.user', function ($query) use ($search) {
//                $query->where('username', 'like', "%$search%")
//                    ->orWhere('employee_id', 'like', "%$search%")
//                    ->orWhere('firstname', 'like', "%$search%")
//                    ->orWhere('lastname', 'like', "%$search%");
//            })->orderBy('created_at', 'desc');
        });

//        if ($status === "deactivated") {
//            $userApproverQuery->onlyTrashed();
//        } elseif ($status === 'active') {
//            $userApproverQuery->whereNull('deleted_at');
//        }

        $transformedResults = $userApproverQuery->get()->groupBy('requester_id')->map(function ($item) {
            return [
                'id' => $item[0]->requester_id,
                'requester_details' => $item[0]->requester,
                'approvers' => $item->map(function ($item) {
                    return [
                        'approver_id' => $item->approver_id,
                        'employee_id' => $item->approver->user->employee_id,
                        'first_name' => $item->approver->user->firstname,
                        'last_name' => $item->approver->user->lastname,
                        'username' => $item->approver->user->username,
                        'is_active' => $item->approver->user->is_active,
                        'role' => $item->approver->user->role->id,
                        'layer' => $item->layer,
                    ];
                })->sortBy('layer')->values(),
            ];
//            return [
//                'requester_id' => $item[0]->requester_id,
//                'requester_details' => $item[0]->requester,
//                'approvers' => $item->map(function ($item) {
//                    return [
//                        'approver_id' => $item->approver_id,
//                        'approver_details' => $item->approver->user,
//                        'layer' => $item->layer,
//                    ];
//                })->sortBy('layer')->values(),
//            ];
        })->values();

        //then check if the limit is not null then paginate the result else return all without pagination
        if ($limit !== null) {
            $page = $request->input('page', 1);
            $offset = ($page * $limit) - $limit;
            $transformedResults = new LengthAwarePaginator(
                array_slice($transformedResults->toArray(), $offset, $limit, true),
                count($transformedResults),
                $limit,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return $this->responseSuccess('Successfully Retrieved!', $transformedResults);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AssignApproverRequest $request): JsonResponse
    {
        $requester_id = $request->requester_id;
        $approver_id = $request->approver_id;

//        //Check Missing layer number in the approver layer
//        $approver = ApproverLayer::where('requester_id', $requester_id)->exists();
//        if ($approver) {
//            //get the count of the approver
//            $approverCount = ApproverLayer::where('requester_id', $requester_id)->count();
//            //check what numbers is missing in the approver layer
//            $missingLayers = [];
//            for ($i = 1; $i <= $approverCount; $i++) {
//                $layer = ApproverLayer::where('requester_id', $requester_id)->where('layer', $i)->exists();
//                if (!$layer) {
//                    array_push($missingLayers, $i);
//                }
//            }
//            return response()->json([
//                'message' => 'Approver Created Successfully',
//                'data' => $missingLayers
//            ], 201);
//        }

        ////This is to re align the layer of the approver if the approver is deleted
        //approver_id is arrayed
        foreach ($approver_id as $value) {
            $layer = ApproverLayer::where('requester_id', $requester_id)->max('layer');
            $createUserApprover = ApproverLayer::create([
                'requester_id' => $requester_id,
                'approver_id' => $value,
                'layer' => $layer + 1,
            ]);
        }
        return response()->json([
            'message' => 'Approver Created Successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $approver = ApproverLayer::where('requester_id', $id)->first();
        if (!$approver) {
            return $this->responseNotFound('Approver Layer Route Not Found');
        }
        //get the requester_id
        $requester_id = $approver->requester_id;
        //get all the approver of the requester
        $userApprover = ApproverLayer::where('requester_id', $requester_id)->get();
        $transformedResults = $userApprover->groupBy('requester_id')->map(function ($item) {
            return [
                'id' => $item[0]->requester_id,
                'requester_details' => $item[0]->requester,
                'approvers' => $item->map(function ($item) {
                    return [
                        'approver_id' => $item->approver_id,
                        'employee_id' => $item->approver->user->employee_id,
                        'first_name' => $item->approver->user->firstname,
                        'last_name' => $item->approver->user->lastname,
                        'username' => $item->approver->user->username,
                        'is_active' => $item->approver->user->is_active,
                        'role' => $item->approver->user->role->id,
                        'layer' => $item->layer,
                    ];
                })->sortBy('layer')->values(),
            ];
        })->values()->first();

        return $this->responseSuccess('Successfully Retrieved!', $transformedResults);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AssignApproverRequest $request, $id): JsonResponse
    {
        $approver_id = $request->approver_id;

        $updateUserApprover = ApproverLayer::where('id', $id)->update([
            'approver_id' => $approver_id,
        ]);

        return response()->json([
            'message' => 'Approver Updated Successfully',
            'data' => $updateUserApprover
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $approverLayers = ApproverLayer::where('requester_id', $id)->get();
        if (!$approverLayers) {
            return $this->responseNotFound('Approver Layer Route Not Found');
        }
        $approverLayers->each(function ($approverLayer) {
            $approverLayer->delete();
        });

        return $this->responseSuccess('Successfully Deleted!', null, 200);


//        $userApprover = ApproverLayer::find($id);
//
//        if (!$userApprover) {
//            return response()->json(['error' => 'Approver Layer Route Not Found'], 404);
//        }
//
//        $requester_id = $userApprover->requester_id;
//        $deletedLayer = $userApprover->layer;
//        $userApprover->delete();
//
//        $higherLayers = ApproverLayer::where('requester_id', $requester_id)->where('layer', '>', $deletedLayer)->get();
//
//        if ($higherLayers->isNotEmpty()) {
//            $higherLayers->each(function ($approverLayer) {
//                $approverLayer->update([
//                    'layer' => $approverLayer->layer - 1,
//                ]);
//            });
//        }
//
//        return response()->json(['message' => 'Successfully Deleted!'], 200);
    }


    public function archived(AssignApproverRequest $request, $id)
    {
        $status = $request->status;

        $UserApprover = ApproverLayer::query();
        if (!$UserApprover->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'User Route Not Found'], 404);
        }
        if ($status == false) {
            if (!ApproverLayer::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $userSettingApproverCheck = ApproverLayer::where('requester_id', $id)->orWhere('approver_id', $id)->exists();
                if ($userSettingApproverCheck) {
                    return response()->json(['message' => 'User Account still in use'], 422);
                }

                $updateStatus = $UserApprover->where('id', $id)->update(['is_active' => false]);
                $UserApprover->where('id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactivated!'], 200);
            }
        }
        if ($status == true) {
            if (ApproverLayer::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                //get the user id from requester_id and approver_id
                $userApprover = ApproverLayer::where('id', $id)->first();

                $userAccountCheck = User::where('id', $userApprover->requester_id)->orWhere('id', $userApprover->approver_id)->exists();
                if (!$userAccountCheck) {
                    return response()->json(['message' => 'User Account does not exist'], 422);
                }


                $restoreUser = $UserApprover->withTrashed()->where('id', $id)->restore();
                $updateStatus = $UserApprover->update(['is_active' => true]);
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
        }

    }

    public function requesterView(): JsonResponse
    {
        $user_id = auth('sanctum')->user()->id;
        $userApprover = ApproverLayer::where('requester_id', $user_id)->get();
        return response()->json([
            'message' => 'Successfully Retrieved!',
            'data' => $userApprover
        ], 200);
    }

    public function arrangeLayer(Request $request, $id): JsonResponse
    {
        $requesterId = $id;
        $approverLayers = $request->approver_id;
        $layer = 1;

        $approverIds = ApproverLayer::where('requester_id', $requesterId)->pluck('approver_id')->toArray();


        $deletableApproverIds = array_diff($approverIds, $approverLayers);
        if(count($deletableApproverIds) > 0) {
            ApproverLayer::where('requester_id', $requesterId)
                ->whereIn('approver_id', $deletableApproverIds)->delete();
        }

        foreach ($approverLayers as $approverId) {
            ApproverLayer::updateOrCreate(
                [
                    'approver_id' => $approverId,
                    'requester_id' => $requesterId
                ],
                ['layer' => $layer++]
            );
        }

        return response()->json(['message' => 'Successfully arranged'], 200);

//        $newLayer = $request->layer;
//
//        // Get the approver layer
//        $approver = ApproverLayer::findOrFail($id);
//
//        $oldLayer = $approver->layer;
//
//        // Get the requester_id from the updated model instance
//        $requester_id = $approver->requester_id;
//
//        if ($newLayer > $oldLayer) {
//            // Moving layer up, so decrement layers between old layer and new layer
//            ApproverLayer::where('requester_id', $requester_id)
//                ->whereBetween('layer', [$oldLayer + 1, $newLayer])
//                ->decrement('layer');
//        } elseif ($newLayer < $oldLayer) {
//            // Moving layer down, so increment layers between new layer and old layer
//            ApproverLayer::where('requester_id', $requester_id)
//                ->whereBetween('layer', [$newLayer, $oldLayer - 1])
//                ->increment('layer');
//        }
//
//        // Then we update the layer
//        $approver->update(['layer' => $newLayer]);
//
//        return response()->json([
//            'message' => 'Successfully Updated!',
//            'data' => $approver
//        ], 200);
    }
}
