<?php

namespace App\Http\Controllers\API;

use App\Models\AssetRequest;
use App\Traits\AddingPrHandler;
use App\Traits\AssetRequestHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

use App\Http\Requests\AddingPr\CreateAddingPrRequest;
use App\Http\Requests\AddingPr\UpdateAddingPrRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use function PHPUnit\Framework\isEmpty;

class AddingPrController extends Controller
{
    use ApiResponse, AssetRequestHandler;

    public function index(Request $request)
    {
        $hasPrNumber = $request->get('has_pr');
        $perPage = $request->input('per_page', null);

        $assetRequest = AssetRequest::where('status', 'Approved')
            ->when($hasPrNumber !== null, function ($query) use ($hasPrNumber) {
                return $hasPrNumber == 1 ? $query->whereNotNull('pr_number') : $query->whereNull('pr_number');
            })->useFilters();


        $assetRequest = $assetRequest->get()->groupBy('transaction_number')->map(function ($assetRequestCollection) {
            $assetRequest = $assetRequestCollection->first();
            //sum all the quantity per group
            $assetRequest->quantity = $assetRequestCollection->sum('quantity');
            return $this->transformIndexAssetRequest($assetRequest);
        })->values();


        if ($perPage !== null) {
            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            $assetRequest = new LengthAwarePaginator(
                $assetRequest->slice($offset, $perPage)->values(),
                $assetRequest->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return $assetRequest;
    }

    public function store(CreateAddingPrRequest $request): JsonResponse
    {
        $assetRequest = AssetRequest::create($request->all());

        return $this->responseCreated('AssetRequest created successfully', $assetRequest);
    }

    public function show(AssetRequest $assetRequest): JsonResponse
    {
        return $this->responseSuccess(null, $assetRequest);
    }

    public function update(UpdateAddingPrRequest $request, $transactionNumber): JsonResponse
    {
        $prNumber = $request->pr_number;
        $businessUnitId = $request->business_unit_id;
        $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('status', 'Approved')->get();
        if ($assetRequests->isEmpty()) {
            return $this->responseUnprocessable('Asset Request is not yet approved');
        }
        $assetRequests->each(function ($assetRequest) use ($prNumber, $businessUnitId) {
            $assetRequest->update([
                'pr_number' => $prNumber,
                'business_unit_id' => $businessUnitId,
            ]);
        });

        return $this->responseSuccess('PR No. added successfully');
    }

    public function destroy(AssetRequest $assetRequest): JsonResponse
    {
        $assetRequest->delete();

        return $this->responseDeleted();
    }

    public function removePR($transactionNumber): JsonResponse
    {
        $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('status', 'Approved')->get();
        if ($assetRequests->isEmpty()) {
            return $this->responseUnprocessable('Asset Request is not yet approved');
        }
        $assetRequests->each(function ($assetRequest) {
            $assetRequest->update([
                'pr_number' => null,
                'business_unit_id' => null,
            ]);
        });

        return $this->responseSuccess('PR No. removed successfully');
    }
}
