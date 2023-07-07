<?php

namespace App\Http\Controllers\Masterlist\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\AssetStatus\AssetStatusRequest;
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
            ->when($status === "deactivated", function ($query) {
                $query->onlyTrashed();
            })
            ->orderByDesc("updated_at");
        $assetStatus = $limit ? $assetStatus->paginate($limit) : $assetStatus->get();

        return $assetStatus;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
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
    public function update(AssetStatusRequest $request, $id)
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
