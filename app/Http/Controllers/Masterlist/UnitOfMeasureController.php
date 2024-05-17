<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\UnitOfMeasure;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class UnitOfMeasureController extends Controller
{
    use ApiResponse;


    public function index(Request $request)
    {
        $uomStatus = $request->status ?? 'active';
        $isActiveStatus = ($uomStatus === 'deactivated') ? 0 : 1;
        $uom = UnitOfMeasure::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();
        return $uom;
    }

    public function store(Request $request)
    {
        $uomData = $request->input('result');
        if (empty($request->all()) || empty($request->input('result'))) {
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach ($uomData as $uoms) {
            $sync_id = $uoms['id'];
            $code = $uoms['code'];
            $name = $uoms['name'];
            $is_active = $uoms['deleted_at'] ? 0 : 1;

            $uom = UnitOfMeasure::updateOrCreate(
                ['sync_id' => $sync_id],
                [
                    'uom_code' => $code,
                    'uom_name' => $name,
                    'is_active' => $is_active
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
