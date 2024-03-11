<?php

namespace App\Http\Controllers\Masterlist\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\AssetStatus\AssetStatusRequest;
use App\Models\FixedAsset;
use App\Models\Status\AssetStatus;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class AssetStatusController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $requestAssetStatus = $request->status ?? 'status';
        $isActiveStatus = ($requestAssetStatus === 'deactivated') ? 0 : 1;

        $assetStatus = AssetStatus::withTrashed()->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPaginate();


        return $assetStatus;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AssetStatusRequest $request)
    {
        $asset_status_name = ucwords(strtolower($request->asset_status_name));

        $assetStatus = AssetStatus::create([
            'asset_status_name' => $asset_status_name
        ]);

//        return response()->json([
//            'message' => 'Successfully created asset status.',
//            'data' => $assetStatus
//        ], 200);

        return $this->responseCreated('Successfully created asset status.');

    }
    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $assetStatus = AssetStatus::find($id);
        if(!$assetStatus){
//            return response()->json([
//                'error' => 'Asset status route not found.'
//            ], 404);
            return $this->responseNotFound('Asset status route not found.');
        }

        return response()->json([
            'message' => 'Successfully retrieved asset status.',
            'data' => $assetStatus
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AssetStatusRequest $request, $id)
    {
        $asset_status_name = ucwords(strtolower($request->asset_status_name));

        $assetStatus = AssetStatus::find($id);
        if (!$assetStatus) {
            return $this->responseNotFound('Asset status route not found.');
        }

        if ($assetStatus->asset_status_name == $asset_status_name) {
//            return response()->json([
//                'message' => 'No changes were made.'
//            ], 200);

            return $this->responseSuccess('No changes were made.');
        }

        $assetStatus->update([
            'asset_status_name' => $asset_status_name
        ]);

//        return response()->json([
//            'message' => 'Successfully updated asset status.',
//            'data' => $assetStatus
//        ], 200);
        return $this->responseSuccess('Successfully updated asset status.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }


    public function archived(AssetStatusRequest $request, $id)
    {

        $status = $request->status;

        $assetStatus = AssetStatus::query();
        if (!$assetStatus->withTrashed()->where('id', $id)->exists()) {
//            return response()->json([
//                'message' => 'Asset Status Route Not Found.'
//            ], 404);
            return $this->responseNotFound('Asset Status Route Not Found.');
        }

        if ($status == false) {
            if (!AssetStatus::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json([
//                    'message' => 'No Changes.'
//                ], 200);
                return $this->responseSuccess('No Changes.');
            } else {
                $checkFixedAsset = FixedAsset::where('asset_status_id', $id)->exists();
                if ($checkFixedAsset) {
//                    return response()->json([
//                        'message' => 'The given data was invalid.',
//                        'errors' => [
//                            'asset_status' => [
//                                'Asset Status is still in use!'
//                            ]
//                        ]
//                    ], 422);
                    return $this->responseUnprocessable( 'Asset Status is still in use!');
                }
                if (AssetStatus::where('id', $id)->exists()) {
                    $updateCapex = AssetStatus::Where('id', $id)->update([
                        'is_active' => false,
                    ]);
                    $archiveCapex = AssetStatus::where('id', $id)->delete();
//                    return response()->json([
//                        'message' => 'Successfully archived Asset Status.',
//                    ], 200);
                    return $this->responseSuccess('Successfully archived Asset Status.');
                }

            }
        }

        if ($status == true) {
            if (AssetStatus::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json([
//                    'message' => 'No Changes.'
//                ], 200);
                return $this->responseSuccess('No Changes.');
            } else {
                $restoreCapex = AssetStatus::withTrashed()->where('id', $id)->restore();
                $updateStatus = AssetStatus::where('id', $id)->update([
                    'is_active' => true,
                ]);
//                return response()->json([
//                    'message' => 'Successfully restored Asset Status.',
//                ], 200);
                return $this->responseSuccess('Successfully restored Asset Status.');
            }
        }
    }
}
