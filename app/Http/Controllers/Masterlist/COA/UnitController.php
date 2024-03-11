<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Unit;
use App\Traits\COA\UnitHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use ApiResponse, UnitHandler;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $unitStatus = $request->status ?? 'active';
        $isActiveStatus = ($unitStatus === 'deactivated') ? 0 : 1;
        $unit = Unit::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();
        return $this->transformUnit($unit);
    }

    public function store(Request $request)
    {
        $department = Department::all()->isEmpty();
        if ($department) {
            return $this->responseUnprocessable('Department Data not Ready');
        }

        $unitData = $request->input('result');
        if (empty($request->all()) || empty($request->input('result'))) {
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach ($unitData as $units) {
            $sync_id = $units['id'];
            $code = $units['code'];
            $name = $units['name'];
            $is_active = $units['deleted_at'];
            $department_sync_id = $units['department']['id'];
            $unit = Unit::updateOrCreate(
                ['sync_id' => $sync_id],
                [
                    'unit_code' => $code,
                    'unit_name' => $name,
                    'is_active' => $is_active == Null ? 1 : 0,
                    'department_sync_id' => $department_sync_id
                ]
            );
        }
        return $this->responseSuccess('Successfully Synced!');
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
}
