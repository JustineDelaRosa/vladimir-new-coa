<?php

namespace App\Http\Controllers\Masterlist\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\AssetStatus\AssetStatusRequest;
use App\Models\FixedAsset;
use App\Models\Status\AssetStatus;
use Illuminate\Http\Request;

class AssetStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->search;
        $status = $request->status;
        $limit = $request->limit;

        $assetStatus = AssetStatus::where(function ($query) use ($search) {
            $query
                ->where("asset_status_name", "like", "%" . $search . "%");
        })
            ->when($request->status === 'deactivated', function ($query) {
                return $query->onlyTrashed();
            })
            ->when($request->status === 'active', function ($query) {
                return $query->whereNull('deleted_at');
            })
            ->orderByDesc('created_at')
            ->when($request->limit, function ($query) use ($request) {
                return $query->paginate($request->limit);
            }, function ($query) {
                return $query->get();
            });


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

        return response()->json([
            'message' => 'Successfully created asset status.',
            'data' => $assetStatus
        ], 200);

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
        if (!$assetStatus) return response()->json([
            'message' => 'Asset status route not found.'
        ], 404);

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
        if (!$assetStatus) return response()->json([
            'error' => 'Asset status route not found.'
        ], 404);

        if ($assetStatus->asset_status_name == $asset_status_name) {
            return response()->json([
                'message' => 'No changes were made.'
            ], 200);
        }

        $assetStatus->update([
            'asset_status_name' => $asset_status_name
        ]);

        return response()->json([
            'message' => 'Successfully updated asset status.',
            'data' => $assetStatus
        ], 200);
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
            return response()->json([
                'message' => 'Asset Status Route Not Found.'
            ], 404);
        }

        if ($status == false) {
            if (!AssetStatus::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            } else {
//                $checkFixedAsset = FixedAsset::where('asset_status_id', $id)->exists();
//                if ($checkFixedAsset) {
//                    return response()->json(['error' => 'Unable to archived , Asset Status is still in use!'], 422);
//                }
                if (AssetStatus::where('id', $id)->exists()) {
                    $updateCapex = AssetStatus::Where('id', $id)->update([
                        'is_active' => false,
                    ]);
                    $archiveCapex = AssetStatus::where('id', $id)->delete();
                    return response()->json([
                        'message' => 'Successfully archived Asset Status.',
                    ], 200);
                }

            }
        }

        if ($status == true) {
            if (AssetStatus::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            } else {
                $restoreCapex = AssetStatus::withTrashed()->where('id', $id)->restore();
                $updateStatus = AssetStatus::where('id', $id)->update([
                    'is_active' => true,
                ]);
                return response()->json([
                    'message' => 'Successfully restored Asset Status.',
                ], 200);
            }
        }
    }
}
