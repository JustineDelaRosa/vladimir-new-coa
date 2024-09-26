<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\SmallTools;
use Illuminate\Http\Request;

class SmallToolsController extends Controller
{

    public function index(Request $request)
    {
        $smallToolsStatus = $request->status ?? 'active';
        $isActiveStatus = ($smallToolsStatus === 'deactivated') ? 0 : 1;

        $smallTools = SmallTools::where('is_active', $isActiveStatus)
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

            $sync = SmallTools::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'small_tool_code' => $code,
                    'small_tool_name' => $name,
                    'is_active' => $is_active == NULL ? 1 : 0,
                ],
            );
        }
//        return response()->json(['message' => 'Successfully Synced!']);
        return $this->responseSuccess('Successfully Synced!');
    }


    public function show(SmallTools $smallTools)
    {
        //
    }


    public function update(Request $request, SmallTools $smallTools)
    {
        //
    }


    public function destroy(SmallTools $smallTools)
    {
        //
    }
}
