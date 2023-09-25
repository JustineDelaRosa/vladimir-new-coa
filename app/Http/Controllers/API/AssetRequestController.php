<?php

namespace App\Http\Controllers\API;

use App\Models\ApproverLayer;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

use App\Http\Requests\AssetRequest\CreateAssetRequestRequest;
use App\Http\Requests\AssetRequest\UpdateAssetRequestRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetRequestController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $assetRequests = AssetRequest::dynamicPaginate();

        return response()->json(['message' => 'AssetRequest retrieved successfully', 'data' => $assetRequests], 200);
    }

    public function store(CreateAssetRequestRequest $request)
    {
        $assetRequest = AssetRequest::create($request->all());

        if($assetRequest){
           $approverLayer = ApproverLayer::where('requester_id', $request->requester_id)->orderBy('layer', 'asc')->get();

            $firstLayerFlag = true; // Introduce a flag to identify the first layer

            foreach ($approverLayer as $layer){
                $approver_id = $layer->approver_id;
                $layer_number = $layer->layer;

                $status = $firstLayerFlag ? 'For Approval' : null;
                $assetApproval = AssetApproval::query();
                $createAssetApproval = $assetApproval->create([
                    'asset_request_id' => $assetRequest->id,
                    'approver_id' => $approver_id,
                    'requester_id' => $request->requester_id,
                    'layer' => $layer_number,
                    'status' => $status,
                ]);
                $firstLayerFlag = false;
            }
            return $this->responseCreated('AssetRequest created successfully', $assetRequest);

        }
        return $this->responseCreated('AssetRequest created successfully', $assetRequest);
    }

    public function show(AssetRequest $assetRequest): JsonResponse
    {
        return $this->responseSuccess(null, $assetRequest);
    }

    public function update(UpdateAssetRequestRequest $request, AssetRequest $assetRequest): JsonResponse
    {
        $assetRequest->update($request->all());

        return $this->responseSuccess('AssetRequest updated Successfully', $assetRequest);
    }

    public function destroy(AssetRequest $assetRequest): JsonResponse
    {
        $assetRequest->delete();

        return $this->responseDeleted();
    }

}
