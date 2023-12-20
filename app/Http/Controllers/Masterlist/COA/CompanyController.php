<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $companyStatus = $request->status ?? 'active';
        $isActiveStatus = ($companyStatus === 'deactivated') ? 0 : 1;

        $company = Company::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();

        return $company;
    }

    public function store(Request $request)
    {
        $companyData = $request->input('result.companies');
        if (empty($request->all()) || empty($request->input('result.companies'))) {
            //            return response()->json(['message' => 'Data not Ready']);
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach ($companyData as $companies) {
            $sync_id = $companies['id'];
            $code = $companies['code'];
            $name = $companies['name'];
            $is_active = $companies['status'];

            $sync = Company::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'company_code' => $code,
                    'company_name' => $name,
                    'is_active' => $is_active
                ],
            );
        }
        //        return response()->json(['message' => 'Successfully Synced!']);
        return $this->responseSuccess('Successfully Synced!');
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
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
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
        $Company = Company::where(function ($query) use ($status) {
            $query->where('is_active', $status);
        })
            ->where(function ($query) use ($search) {
                $query->where('company_code', 'LIKE', "%{$search}%")
                    ->orWhere('company_name', 'LIKE', "%{$search}%");
            })
            ->orderby('created_at', 'DESC')
            ->paginate($limit);
        return $Company;
    }

    //    public function archived(Request $request, $id)
    //    {
    //        $status = $request->status;
    //        $Company = Company::query();
    //        if (!$Company->where('id', $id)->exists()) {
    //            return response()->json(['error' => 'Company Route Not Found'], 404);
    //        }
    //
    //
    //        if ($status == false) {
    //            if (!Company::where('id', $id)->where('is_active', true)->exists()) {
    //                return response()->json(['message' => 'No Changes'], 200);
    //            } else {
    //                $checkDepartment = Department::where('company_id', $id)->exists();
    //                if ($checkDepartment) {
    //                    return response()->json(['error' => 'Unable to archived , Company is still in use!'], 422);
    //                }
    //
    //                $updateStatus = $Company->where('id', $id)->update(['is_active' => false]);
    ////                $Company->where('id', $id)->delete();
    //                return response()->json(['message' => 'Successfully Deactived!'], 200);
    //            }
    //        }
    //        if ($status == true) {
    //            if (Company::where('id', $id)->where('is_active', true)->exists()) {
    //                return response()->json(['message' => 'No Changes'], 200);
    //            } else {
    ////              $restoreUser = $Company->withTrashed()->where('id', $id)->restore();
    //                $updateStatus = $Company->update(['is_active' => true]);
    //                return response()->json(['message' => 'Successfully Activated!'], 200);
    //            }
    //        }
    //    }
}
