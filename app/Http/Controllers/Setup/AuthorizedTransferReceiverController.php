<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthorizedTransferReceiver\CreateAuthorizedTransferReceiver;
use App\Models\AuthorizedTransferReceiver;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class AuthorizedTransferReceiverController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->input('status');
        $isActiveStatus = $status === 'active' ? 1 : 0;

        $authorizedTransferReceivers = AuthorizedTransferReceiver::withTrashed()->where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();

        $authorizedTransferReceivers->transform(function ($authorizedTransferReceiver) {
            return [
                'id' => $authorizedTransferReceiver->id,
                'user' => [
                    'id' => $authorizedTransferReceiver->user->id,
                    'employee_id' => $authorizedTransferReceiver->user->employee_id,
                    'firstname' => $authorizedTransferReceiver->user->firstname,
                    'lastname' => $authorizedTransferReceiver->user->lastname,
                    'username' => $authorizedTransferReceiver->user->username,
                    'is_coordinator' => $authorizedTransferReceiver->user->is_coordinator,
                    'full_id_number_full_name' => $authorizedTransferReceiver->user->employee_id . ' - ' . $authorizedTransferReceiver->user->firstname . ' ' . $authorizedTransferReceiver->user->lastname,

                ],
                'company' => [
                    'id' => $authorizedTransferReceiver->user->company->id ?? '-',
                    'company_code' => $authorizedTransferReceiver->user->company->company_code ?? '-',
                    'company_name' => $authorizedTransferReceiver->user->company->company_name ?? '-',
                ],
                'business_unit' => [
                    'id' => $authorizedTransferReceiver->user->businessUnit->id ?? '-',
                    'business_unit_code' => $authorizedTransferReceiver->user->businessUnit->business_unit_code ?? '-',
                    'business_unit_name' => $authorizedTransferReceiver->user->businessUnit->business_unit_name ?? '-',
                ],
                'department' => [
                    'id' => $authorizedTransferReceiver->user->department->id ?? '-',
                    'department_code' => $authorizedTransferReceiver->user->department->department_code ?? '-',
                    'department_name' => $authorizedTransferReceiver->user->department->department_name ?? '-',
                ],
                'unit' => [
                    'id' => $authorizedTransferReceiver->user->unit->id ?? '-',
                    'unit_code' => $authorizedTransferReceiver->user->unit->unit_code ?? '-',
                    'unit_name' => $authorizedTransferReceiver->user->unit->unit_name ?? '-',
                ],
                'subunit' => [
                    'id' => $authorizedTransferReceiver->user->subunit->id ?? '-',
                    'subunit_code' => $authorizedTransferReceiver->user->subunit->sub_unit_code ?? '-',
                    'subunit_name' => $authorizedTransferReceiver->user->subunit->sub_unit_name ?? '-',
                ],
                'location' => [
                    'id' => $authorizedTransferReceiver->user->location->id ?? '-',
                    'location_code' => $authorizedTransferReceiver->user->location->location_code ?? '-',
                    'location_name' => $authorizedTransferReceiver->user->location->location_name ?? '-',
                ],
                'is_active' => $authorizedTransferReceiver->is_active,
            ];
        });

        return $authorizedTransferReceivers;
    }

    public function store(CreateAuthorizedTransferReceiver $request)
    {
        $userId = $request->input('user_id');

        $authorizedTransferReceiver = AuthorizedTransferReceiver::create([
            'user_id' => $userId
        ]);

        return $this->responseSuccess('Authorized Transfer Receiver created successfully', $authorizedTransferReceiver);
    }


    public function show($id)
    {
        //;
    }


    public function update(Request $request, $id)
    {
        $authorizedTransferReceiver = AuthorizedTransferReceiver::findOrFail($id);

        $authorizedTransferReceiver->update([
            'is_active' => $request->input('is_active')
        ]);

        return $this->responseSuccess('Authorized Transfer Receiver updated successfully', $authorizedTransferReceiver);
    }


    public function archived(Request $request, $id)
    {
        $status = $request->input('status');
        $authorizedTransferReceiver = AuthorizedTransferReceiver::query()->withTrashed();
        if (!$authorizedTransferReceiver->where('id', $id)->exists()) {
            return $this->responseNotFound('Authorized Transfer Receiver not found');
        }


        if ($status == false) {
            if (!$authorizedTransferReceiver->where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No Changes Made');
            } else {
                $authorizedTransferReceiver = AuthorizedTransferReceiver::find($id);

                $authorizedTransferReceiver->update([
                    'is_active' => false
                ]);
                $authorizedTransferReceiver->delete();
                return $this->responseSuccess('Archived Successfully');
            }
        }

        if($status == true){
            if($authorizedTransferReceiver->where('id', $id)->where('is_active', true)->exists()){
                return $this->responseSuccess('No Changes Made');
            }else{
                $authorizedTransferReceiver = AuthorizedTransferReceiver::withTrashed()->find($id);
                $authorizedTransferReceiver->restore();
                $authorizedTransferReceiver->update([
                    'is_active' => true
                ]);
                return $this->responseSuccess('Activated Successfully');
            }
        }
    }
}
