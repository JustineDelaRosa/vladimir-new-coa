<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Company;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class BusinessUnitController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $businessUnitStatus = $request->status ?? 'active';
        $isActiveStatus = ($businessUnitStatus === 'deactivated') ? 0 : 1;

        $businessUnit = BusinessUnit::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();

        return $businessUnit;
    }

    public function store(Request $request)
    {
        $company = Company::all()->isEmpty();
        if ($company) {
//            return response()->json(['message' => 'Company Data not Ready'], 422);
            return $this->responseUnprocessable('Company Data not Ready');
        }

        $businessUnitData = $request->input('result.business_units');
        if(empty($request->all())|| empty($request->input('result.business_units'))){
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach($businessUnitData as $businessUnits){
            $sync_id = $businessUnits['id'];
            $company_sync_id = $businessUnits['company']['id'];
            $code = $businessUnits['code'];
            $name = $businessUnits['name'];
            $is_active = $businessUnits['status'];

            $sync = BusinessUnit::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'company_sync_id' => $company_sync_id,
                    'business_unit_code' => $code,
                    'business_unit_name' => $name,
                    'is_active' => $is_active
                ],
            );
        }
        return $this->responseSuccess('Business Unit Data Successfully Synced');
    }


    public function show($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
