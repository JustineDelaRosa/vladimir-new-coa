<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $company = Company::get();
        return $company;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $company_request = $request->all('result.companies');
        if (empty($request->all())) {
            return response()->json(['message' => 'Data not Ready']);
        }


        foreach ($company_request as $companies) {
            foreach ($companies as $company) {
                foreach ($company as $com) {
                    $sync_id = $com['id'];
                    $code = $com['code'];
//                    $name = strtoupper($com['name']);
                    $name = $com['name'];
                    $is_active = $com['status'];

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
                    // $sync = Company::upsert([
                    //     ['company_code' => $code, 'company_name' => $name,  'is_active' => $is_active]
                    //     ], ['company_code'], ['is_active']);

                }
            }
        }
        return response()->json(['message' => 'Successfully Synched!']);
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
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
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
}
