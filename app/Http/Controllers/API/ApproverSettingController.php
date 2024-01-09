<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproverSetting\ApproverSettingRequest;
use App\Models\Approvers;
use App\Models\DepartmentUnitApprovers;
use App\Models\User;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApproverSettingController extends Controller
{

    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {

        $perPage = $request->input('per_page', null);
        $approverSettingStatus = $request->status ?? 'active';
        $isActiveStatus = ($approverSettingStatus === 'deactivated') ? 0 : 1;

        $ApproversQuery = Approvers::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();



        $ApproversQuery->transform(function ($item) {
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

        return $ApproversQuery;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ApproverSettingRequest $request): JsonResponse
    {
        $approver_id = $request->approver_id;
        $user = User::query()->where('id', $approver_id)->first();
        $createApprovers = Approvers::create([
            'approver_id' => $approver_id,
            //            'full_name' => $user->firstname . ' ' . $user->lastname,
        ]);
        return $this->responseSuccess('Successfully Added!');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        $approver = Approvers::find($id);
        if (!$approver) {
            return $this->responseNotFound('Approver Route Not Found.');
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

        return $result;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ApproverSettingRequest $request, $id): JsonResponse
    {
        $approver_id = $request->approver_id;
        $approvers = Approvers::find($id);
        if (!$approvers) {
            return $this->responseNotFound('Approver Route Not Found.');
        }

        if ($approvers->approver_id == $approver_id) {
            return $this->responseSuccess('No Changes');
        }

        $updateUserApprover = Approvers::where('id', $id)->update([
            'approver_id' => $approver_id,
        ]);

        return $this->responseSuccess('Successfully Updated!');
    }

    public function archived(ApproverSettingRequest $request, $id)
    {
        $status = $request->status;

        $approver = Approvers::query();
        if (!$approver->withTrashed()->where('id', $id)->exists()) {
            return $this->responseNotFound('Approver Route Not Found.');
        }
        if ($status == false) {
            if (!Approvers::where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No Changes');
            } else {
                $department_unit_approver = DepartmentUnitApprovers::where('approver_id', $id)->exists();
                if ($department_unit_approver) {
                    return $this->responseUnprocessable('Unable to deactivate, Approver is still in use.');
                }

                $updateStatus = $approver->where('id', $id)->update(['is_active' => false]);
                $approver->where('id', $id)->delete();
                return $this->responseSuccess('Successfully Deactivated!');
            }
        }
        if ($status == true) {
            if (Approvers::where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No Changes');
            } else {
                $approvers = Approvers::withTrashed()->where('id', $id)->first();

                $userAccountCheck = User::Where('id', $approvers->approver_id)->exists();
                if (!$userAccountCheck) {
                    return $this->responseUnprocessable('Unable to activate');
                }


                $approver->withTrashed()->where('id', $id)->restore();
                $approver->update(['is_active' => true]);
                return $this->responseSuccess('Successfully Activated!');
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
