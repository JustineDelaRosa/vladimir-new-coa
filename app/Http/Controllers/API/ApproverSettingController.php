<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproverSetting\ApproverSettingRequest;
use App\Models\Approvers;
use App\Models\DepartmentUnitApprovers;
use App\Models\User;
use Illuminate\Http\Request;

class ApproverSettingController extends Controller
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

        $ApproversQuery = Approvers::with([
            'user' => function ($query) {
                $query->withTrashed();
            },
        ])->where(function ($query) use ($search) {
            $query->orwhereHas('user', function ($query) use ($search) {
                $query->where('username', 'like', '%' . $search . '%')
                    ->orWhere('employee_id', 'like', '%' . $search . '%');
            });
        })->orderBy('created_at', 'desc');

        if ($status === "deactivated") {
            $ApproversQuery->onlyTrashed();
        } elseif ($status === 'active') {
            $ApproversQuery->whereNull('deleted_at');
        }

        if ($limit !== null) {
            $result = is_numeric($limit) ? $ApproversQuery->paginate($limit) : $ApproversQuery->paginate(PHP_INT_MAX);
        } else {
            $result = $ApproversQuery->get();
        }

        $result->transform(function ($item) {
            return [
                'id' => $item->id,
                'approver' => [
                    'id' => $item->user->id,
                    'username' => $item->user->username,
                    'employee_id' => $item->user->employee_id,
                    'firstname' => $item->user->firstname,
                    'lastname' => $item->user->lastname,
                ],
                'department' => [
                    'id' => $item->user->department->id ?? '-',
                    'department_code' => $item->user->department->department_code ?? '-',
                    'department_name' => $item->user->department->department_name ?? '-',
                ],
                'subunit' => [
                    'id' => $item->user->subUnit->id ?? '-',
                    'subunit_code' => $item->user->subUnit->sub_unit_code ?? '-',
                    'subunit_name' => $item->user->subUnit->sub_unit_name ?? '-',
                ],
                'status' => $item->is_active,
                'deleted_at' => $item->deleted_at,
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
    public function store(ApproverSettingRequest $request)
    {
        $approver_id = $request->approver_id;
        $user = User::query()->where('id', $approver_id)->first();
        $createApprovers = Approvers::create([
            'approver_id' => $approver_id,
//            'full_name' => $user->firstname . ' ' . $user->lastname,
        ]);
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
    public function show($id)
    {
        $approver = Approvers::find($id);
        if (!$approver) {
            return response()->json([
                'error' => 'Approver Route Not Found.'
            ], 404);
        }
        $result = [
            'id' => $approver->id,
            'approver' => [
                'id' => $approver->user->id,
                'username' => $approver->user->username,
                'employee_id' => $approver->user->employee_id,
                'firstname' => $approver->user->firstname,
                'lastname' => $approver->user->lastname,
            ],
            'full_name' => $approver->user->firstname . ' ' . $approver->user->lastname,
            'status' => $approver->is_active,
            'deleted_at' => $approver->deleted_at,
            'created_at' => $approver->created_at,
            'updated_at' => $approver->updated_at,
        ];

        return response()->json([
            'message' => 'Successfully Retrieved!',
            'data' => $result
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ApproverSettingRequest $request, $id)
    {
        $approver_id = $request->approver_id;
        $approvers = Approvers::find($id);
        if (!$approvers) {
            return response()->json([
                'error' => 'Approvers Route Not Found.'
            ], 404);
        }

        if ($approvers->approver_id == $approver_id) {
            return response()->json([
                'message' => 'No changes.',
            ], 200);
        }

        $updateUserApprover = Approvers::where('id', $id)->update([
            'approver_id' => $approver_id,
        ]);

        return response()->json([
            'message' => 'Approver Updated Successfully',
        ], 201);
    }

    public function archived(ApproverSettingRequest $request, $id)
    {
        $status = $request->status;

        $approver = Approvers::query();
        if (!$approver->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'User Route Not Found'], 404);
        }
        if ($status == false) {
            if (!Approvers::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $department_unit_approver = DepartmentUnitApprovers::where('approver_id', $id)->exists();
                if ($department_unit_approver) {
                    return response()->json(['error' => 'Unable to deactivate'], 422);
                }

                $updateStatus = $approver->where('id', $id)->update(['is_active' => false]);
                $approver->where('id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactivated!'], 200);
            }
        }
        if ($status == true) {
            if (Approvers::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $approvers = Approvers::withTrashed()->where('id', $id)->first();

                $userAccountCheck = User::Where('id', $approvers->approver_id)->exists();
                if (!$userAccountCheck) {
                    return response()->json(['error' => 'Unable to activate'], 422);
                }


                $approver->withTrashed()->where('id', $id)->restore();
                $approver->update(['is_active' => true]);
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
        }
    }

    public function approverSetting()
    {
        $user = User::whereDoesntHave('approvers', function ($query) {
            $query->withTrashed();
        })->get();
        return $user;
    }
}
