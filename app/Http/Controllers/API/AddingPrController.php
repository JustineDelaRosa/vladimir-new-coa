<?php

namespace App\Http\Controllers\API;


use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Traits\AddingPRHandler;
use App\Traits\RequestShowDataHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\AssetRequestHandler;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
use function PHPUnit\Framework\isEmpty;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\AddingPr\CreateAddingPrRequest;
use App\Http\Requests\AddingPr\UpdateAddingPrRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddingPrController extends Controller
{
    use ApiResponse, AssetRequestHandler, AddingPRHandler, RequestShowDataHandler;

    public function index(Request $request)
    {
        $toPr = $request->get('toPr', null);
        $perPage = $request->input('per_page', null);

        $assetRequest = AssetRequest::where('status', 'Approved')
            ->when($toPr !== null, function ($query) use ($toPr) {
                return $query->where($toPr == 0 ? 'pr_number' : 'pr_number', $toPr == 0 ? '!=' : '=', null);
            })
            ->useFilters()
            ->get()
            ->groupBy('transaction_number')
            ->map(function ($assetRequestCollection) {
                $assetRequest = $assetRequestCollection->first();
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                return $this->transformIndexAssetRequest($assetRequest);
            })
            ->values();

        if ($perPage !== null) {
            $page = $request->input('page', 1);
            $offset = $page * $perPage - $perPage;
            $assetRequest = new LengthAwarePaginator($assetRequest->slice($offset, $perPage)->values(), $assetRequest->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        return $assetRequest;
    }

    public function store(CreateAddingPrRequest $request): JsonResponse
    {
        $assetRequest = AssetRequest::create($request->all());

        return $this->responseCreated('AssetRequest created successfully', $assetRequest);
    }

    public function show(Request $request, $transactionNumber)
    {
        $requiredRole = array_map('strtolower', ['Purchase Request', 'Admin', 'Super Admin', 'Etd Manager']);
        $userRole = strtolower(auth('sanctum')->user()->role->role_name);

        if (in_array($userRole, $requiredRole)) {
        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->dynamicPaginate();
        } else {
            return $this->responseUnprocessable('You are not allowed to view this transaction.');
        }
        $assetRequest = $this->responseData($assetRequest);

        if ($assetRequest->isEmpty()) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        return $assetRequest;
    }

    public function update(UpdateAddingPrRequest $request, $transactionNumber): JsonResponse
    {
        $prNumber = $request->pr_number;
        $businessUnitId = $request->business_unit_id;
        $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('status', 'Approved')
            ->get();
        if ($assetRequests->isEmpty()) {
            return $this->responseUnprocessable('Asset Request is not yet approved');
        }
        $assetRequests->each(function ($assetRequest) use ($prNumber, $businessUnitId) {
            $assetRequest->update([
                'pr_number' => $prNumber,
                'business_unit_id' => $businessUnitId,
            ]);
        });
        $this->activityLog($assetRequests, $prNumber);

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
            ->where('status', 'Approved')
            ->where('po_number', null)
            ->get();
        if ($assetRequests->isEmpty()) {
            return $this->responseUnprocessable('You cannot remove PR No. already has PO No.');
        }
        $assetRequests->each(function ($assetRequest) {
            $assetRequest->update([
                'pr_number' => null,
                'business_unit_id' => null,
            ]);
        });
        $this->activityLog($assetRequests, null);

        return $this->responseSuccess('PR No. removed successfully');
    }
}
