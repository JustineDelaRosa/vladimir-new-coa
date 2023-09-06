<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproverSetting\ApproverSettingRequest;
use App\Models\User;
use App\Models\UserApprover;
use Illuminate\Http\Request;

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

        $userApproverQuery = UserApprover::where(function ($query) use($search, $limit){
            $query->whereHas('requester', function ($query) use($search){
                $query->where('username', 'like', "%$search%")
                    ->orWhere('employee_id', 'like', "%$search%")
                    ->orWhere('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%");

            })->orWhereHas('approver', function ($query) use($search){
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
                    'id' => $item->approver->id,
                    'username' => $item->approver->username,
                    'employee_id' => $item->approver->employee_id,
                    'firstname' => $item->approver->firstname,
                    'lastname' => $item->approver->lastname,
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ApproverSettingRequest $request)
    {
        $requester_id = $request->requester_id;
        $approver_id = $request->approver_id;
        //approver_id is array
        foreach ($approver_id as $value) {
            $createUserApprover = UserApprover::create([
                'requester_id' => $requester_id,
                'approver_id' => $value,
            ]);

        }
        return response()->json([
            'message' => 'Approver Created Successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ApproverSettingRequest $request, $id)
    {
        $approver_id = $request->approver_id;

        $updateUserApprover = UserApprover::where('id', $id)->update([
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
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $userApprover = UserApprover::where('id', $id)->first();
        if (!$userApprover) {
            return response()->json(['error' => 'User Route Not Found'], 404);
        }
        $userApprover->delete();
        return response()->json(['message' => 'Successfully Deleted!'], 200);
    }


    public function archived(ApproverSettingRequest $request, $id)
    {
        $status = $request->status;

        $UserApprover = UserApprover::query();
        if (!$UserApprover->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'User Route Not Found'], 404);
        }
        if ($status == false) {
            if (!UserApprover::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $userSettingApproverCheck = UserApprover::where('requester_id', $id)->orWhere('approver_id', $id)->exists();
                if ($userSettingApproverCheck) {
                    return response()->json(['message' => 'User Account still in use'], 422);
                }

                $updateStatus = $UserApprover->where('id', $id)->update(['is_active' => false]);
                $UserApprover->where('id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactivated!'], 200);
            }
        }
        if ($status == true) {
            if (UserApprover::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                //get the user id from requester_id and approver_id
                $userApprover = UserApprover::where('id', $id)->first();

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

    public function requesterView(){
        $user_id = auth('sanctum')->user()->id;
        $userApprover = UserApprover::where('requester_id', $user_id)->get();
        return response()->json([
            'message' => 'Successfully Retrieved!',
            'data' => $userApprover
        ], 200);
    }
}
