<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Item;
use App\Models\SmallTools;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;

class SmallToolsController extends Controller
{

    public function index(Request $request)
    {
        $smallToolsStatus = $request->status ?? 'active';
        $isActiveStatus = ($smallToolsStatus === 'deactivated') ? 0 : 1;

        $smallTools = SmallTools::with(['item', 'uom'])->where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();

        $smallTools->transform(function ($smallTool) {
            return [
                'id' => $smallTool->id,
                'sync_id' => $smallTool->sync_id,
                'uom' => [
                    'id' => $smallTool->uom->sync_id,
                    'uom_code' => $smallTool->uom->uom_code,
                    'uom_name' => $smallTool->uom->uom_name,
                ],
                'small_tool_code' => $smallTool->small_tool_code,
                'small_tool_name' => $smallTool->small_tool_name,
                'is_active' => $smallTool->is_active,
                'items' => $smallTool->item->map(function ($item) {
                    return [
                        'id' => $item->sync_id,
                        'sync_id' => $item->sync_id,
                        'item_code' => $item->item_code,
                        'item_name' => $item->item_name,
                        'is_active' => $item->is_active,
                    ];
                })
            ];
        });
        return $smallTools;
    }


    public function store(Request $request)
    {

        $item = Item::all()->isEmpty();
        if ($item) {
//            return response()->json(['message' => 'Company Data not Ready'], 422);
            return $this->responseUnprocessable('Item Data not Ready');
        }

        $smallToolsData = $request->input('result');
        if (empty($request->all()) || empty($request->input('result'))) {
//            return response()->json(['message' => 'Data not Ready']);
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach ($smallToolsData as $smallTool) {

//            return $itemIds = array_column($smallTool['small_tools'], 'id');
            $uomId = UnitOfMeasure::where('sync_id', $smallTool['uom_id'])->first()->id;
            $sync_id = $smallTool['id'];
            $code = $smallTool['code'];
            $name = $smallTool['name'];
            $is_active = $smallTool['deleted_at'];

            $sync = SmallTools::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'uom_id' => $uomId,
                    'small_tool_code' => $code,
                    'small_tool_name' => $name,
                    'is_active' => $is_active == NULL ? 1 : 0,
                ],
            );
            $itemIds = array_column($smallTool['small_tools'], 'small_tools_id');
            foreach ($itemIds as $itemId) {
                if (!Item::where('sync_id', $itemId)->exists()) {
                    return $this->responseUnprocessable('Some of item is missing sync the item first!');
                }
            }
            $sync->item()->sync($itemIds);
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
