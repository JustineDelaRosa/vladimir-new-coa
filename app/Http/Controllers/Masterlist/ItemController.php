<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\SmallTools;
use App\Models\UnitOfMeasure;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $smallToolsStatus = $request->status ?? 'active';
        $isActiveStatus = ($smallToolsStatus === 'deactivated') ? 0 : 1;

        $smallTools = Item::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();
        return $smallTools;
    }

    public function store(Request $request)
    {
        $smallToolsData = $request->input('result');
        if (empty($request->all()) || empty($request->input('result'))) {
//            return response()->json(['message' => 'Data not Ready']);
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach ($smallToolsData as $smallTool) {
            $sync_id = $smallTool['id'];
            $code = $smallTool['code'];
            $name = $smallTool['name'];
            $is_active = $smallTool['deleted_at'];

            $sync = Item::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'item_code' => $code,
                    'item_name' => $name,
                    'is_active' => $is_active == NULL ? 1 : 0,
                ],
            );
        }
//        return response()->json(['message' => 'Successfully Synced!']);
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
