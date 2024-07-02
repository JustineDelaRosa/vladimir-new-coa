<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\CreateWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Models\AssetRequest;
use App\Models\Warehouse;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{

    use ApiResponse;

    public function index(Request $request)
    {
        $rWarehouseStatus = $request->status ?? 'status';
        $isActiveStatus = ($rWarehouseStatus === 'deactivated') ? 0 : 1;

        $rWarehouse = Warehouse::withTrashed()->with('location')->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPaginate();

        return $rWarehouse;
    }


    public function store(CreateWarehouseRequest $request)
    {
        $warehouse_name = ucwords(strtolower($request->warehouse_name));
        $locationId = $request->location_id;

        Warehouse::create([
            'warehouse_name' => $warehouse_name,
            'location_id' => $locationId
        ]);

        return $this->responseCreated('Successfully created warehouse.');

    }


    public function show($id)
    {
        $rWarehouse = Warehouse::find($id);
        if (!$rWarehouse) {
            return $this->responseNotFound('Warehouse not found.');
        }
        return $this->responseSuccess('Warehouse found.', $rWarehouse);
    }


    public function update(UpdateWarehouseRequest $request, $id)
    {
        $warehouseName = ucwords(strtolower($request->warehouse_name));
        $locationId = $request->location_id;
        $rWarehouse = Warehouse::find($id);
        if (!$rWarehouse) {
            return $this->responseNotFound('Warehouse not found.');
        }
        if ($rWarehouse->warehouse_name == $warehouseName && $rWarehouse->location_id == $locationId) {
            return $this->responseSuccess('No changes were made.');
        }
        $rWarehouse->update([
            'warehouse_name' => $warehouseName,
            'location_id' => $locationId
        ]);

        return $this->responseSuccess('Warehouse updated successfully.');
    }


    public function destroy($id)
    {

    }

    public function archived(Request $request, $id)
    {

        $status = $request->status;
        $rWarehouse = Warehouse::query();
        if (!$rWarehouse->withTrashed()->where('id', $id)->exists()) {
            return $this->responseNotFound('Warehouse not found.');
        }

        if ($status == false) {
            if (!Warehouse::where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseBadRequest('No Changes.');
            } else {
                $checkAssetRequest = AssetRequest::where('receiving_warehouse_id', $id)->where('filter', '!=', 'Claimed')->exists();
                if ($checkAssetRequest) {
                    return $this->responseBadRequest('Warehouse cannot be deactivated. There are still pending asset requests.');
                }
                if (Warehouse::where('id', $id)->exists()) {
                    Warehouse::where('id', $id)->update([
                        'is_active' => false
                    ]);
                    Warehouse::where('id', $id)->delete();
                    return $this->responseSuccess('Warehouse deactivated successfully.');
                }
            }
        }

        if ($status == true) {
            if (Warehouse::where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No Changes');
            }else{
                Warehouse::withTrashed()->where('id', $id)->restore();
                Warehouse::where('id', $id)->update([
                    'is_active' => true
                ]);
                return $this->responseSuccess('Warehouse activated successfully.');
            }
        }
    }
}
