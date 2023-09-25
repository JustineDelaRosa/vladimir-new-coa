<?php

namespace App\Http\Controllers\API;

use App\Models\AssetApproval;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

use App\Http\Requests\AssetApproval\CreateAssetApprovalRequest;
use App\Http\Requests\AssetApproval\UpdateAssetApprovalRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetApprovalController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $assetApprovals = AssetApproval::dynamicPaginate();

        return $assetApprovals;
    }

    public function store(CreateAssetApprovalRequest $request): JsonResponse
    {
        $assetApproval = AssetApproval::create($request->all());

        return $this->responseCreated('AssetApproval created successfully', $assetApproval);
    }

    public function show(AssetApproval $assetApproval): JsonResponse
    {
        return $this->responseSuccess(null, $assetApproval);
    }

    public function update(UpdateAssetApprovalRequest $request, AssetApproval $assetApproval): JsonResponse
    {
        $assetApproval->update($request->all());

        return $this->responseSuccess('AssetApproval updated Successfully', $assetApproval);
    }

    public function destroy(AssetApproval $assetApproval): JsonResponse
    {
        $assetApproval->delete();

        return $this->responseDeleted();
    }

}
