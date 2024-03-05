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

        $businessUnitData = $request->input('result');
        if(empty($request->all())|| empty($request->input('result'))){
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach($businessUnitData as $businessUnits){
            $sync_id = $businessUnits['id'];
            $company_sync_id = $businessUnits['company_id'];
            $code = $businessUnits['code'];
            $name = $businessUnits['name'];
            $is_active = $businessUnits['deleted_at'];

//            // Check if the company exists
//            $company = Company::find($company_sync_id);
//            if (!$company) {
//                return $this->responseUnprocessable('Company with sync_id ' . $company_sync_id . ' does not exist');
//            }

            $sync = BusinessUnit::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'company_sync_id' => $company_sync_id,
                    'business_unit_code' => $code,
                    'business_unit_name' => $name,
                    'is_active' => $is_active == NULL ? 1 : 0,
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
