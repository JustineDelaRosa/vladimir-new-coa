<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssigningApprover\AssigningApproverRequest;
use App\Models\User;
use App\Models\ApproverLayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssigningApproverController extends Controller
{
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

            })->orWhereHas('approver.user', function ($query) use ($search) {
                $query->where('username', 'like', "%$search%")
                    ->orWhere('employee_id', 'like', "%$search%")
                    ->orWhere('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%");
            })->orderBy('created_at', 'desc');
        });

//        if ($status === "deactivated") {
//            $userApproverQuery->onlyTrashed();
//        } elseif ($status === 'active') {
//            $userApproverQuery->whereNull('deleted_at');
//        }

        if ($limit !== null) {
            $result = is_numeric($limit) ? $userApproverQuery->paginate($limit) : $userApproverQuery->paginate(PHP_INT_MAX);
        } else {
            $result = $userApproverQuery->get();
        }

        $result->transform(function ($item) {
            return [
                'id' => $item->id,
                'requester' => [
                    'id' => $item->requester->id,
                    'username' => $item->requester->username,
                    'employee_id' => $item->requester->employee_id,
                    'firstname' => $item->requester->firstname,
                    'lastname' => $item->requester->lastname,
                ],

                'approver' => [
                    'id' => $item->approver->user->id,
                    'username' => $item->approver->user->username,
                    'employee_id' => $item->approver->user->employee_id,
                    'firstname' => $item->approver->user->firstname,
                    'lastname' => $item->approver->user->lastname,
                ],
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Successfully Retrieved!',
            'data' => $result
        ], 200);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AssigningApproverRequest $request)
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
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AssigningApproverRequest $request, $id)
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
    public function destroy($id)
    {
        $userApprover = ApproverLayer::find($id);

        if (!$userApprover) {
            return response()->json(['error' => 'Approver Layer Route Not Found'], 404);
        }

        $requester_id = $userApprover->requester_id;
        $deletedLayer = $userApprover->layer;
        $userApprover->delete();

        $higherLayers = ApproverLayer::where('requester_id', $requester_id)->where('layer', '>', $deletedLayer)->get();

        if ($higherLayers->isNotEmpty()) {
            $higherLayers->each(function ($approverLayer) {
                $approverLayer->update([
                    'layer' => $approverLayer->layer - 1,
                ]);
            });
        }

        return response()->json(['message' => 'Successfully Deleted!'], 200);
    }


    public function archived(AssigningApproverRequest $request, $id)
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

    public function requesterView()
    {
        $user_id = auth('sanctum')->user()->id;
        $userApprover = ApproverLayer::where('requester_id', $user_id)->get();
        return response()->json([
            'message' => 'Successfully Retrieved!',
            'data' => $userApprover
        ], 200);
    }

    public function arrangeLayer(Request $request, int $id)
    {
        $newLayer = $request->layer;

        // Get the approver layer
        $approver = ApproverLayer::findOrFail($id);

        $oldLayer = $approver->layer;

        // Get the requester_id from the updated model instance
        $requester_id = $approver->requester_id;

        if ($newLayer > $oldLayer) {
            // Moving layer up, so decrement layers between old layer and new layer
            ApproverLayer::where('requester_id', $requester_id)
                ->whereBetween('layer', [$oldLayer + 1, $newLayer])
                ->decrement('layer');
        } elseif ($newLayer < $oldLayer) {
            // Moving layer down, so increment layers between new layer and old layer
            ApproverLayer::where('requester_id', $requester_id)
                ->whereBetween('layer', [$newLayer, $oldLayer - 1])
                ->increment('layer');
        }

        // Then we update the layer
        $approver->update(['layer' => $newLayer]);

        return response()->json([
            'message' => 'Successfully Updated!',
            'data' => $approver
        ], 200);
    }
}
