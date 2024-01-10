<?php

namespace App\Traits;

use App\Models\AssetRequest;
use Illuminate\Pagination\LengthAwarePaginator;

trait AddingPoHandler
{
    public function createAssetRequestQuery($toPo)
    {
        return AssetRequest::where('status', 'Approved')
            ->wherenotnull('pr_number')
            ->when($toPo !== null, function ($query) use ($toPo) {
                return $query->whereNotIn('transaction_number', function ($query) use ($toPo) {
                    $query->select('transaction_number')
                        ->from('asset_requests')
                        ->groupBy('transaction_number')
                        ->havingRaw('SUM(quantity) ' . ($toPo == 1 ? '=' : '!=') . ' SUM(quantity_delivered)');
                });
            })
            ->useFilters();
    }

    public function paginate($request, $assetRequest, $perPage)
    {
        $page = $request->input('page', 1);
        $offset = $page * $perPage - $perPage;

        return new LengthAwarePaginator(
            $assetRequest->slice($offset, $perPage)->values(),
            $assetRequest->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    public function activityLogPo($assetRequest, $poNumber, $rrnumber)
    {
        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        activity()
            ->causedBy($user)
            ->performedOn($assetRequests)
            ->withProperties($this->composeLogPropertiesPo($assetRequest, $poNumber, $rrnumber))
            ->inLog($poNumber === null ? 'Removed PO Number' : 'Added PO Number')
            ->tap(function ($activity) use ($user, $assetRequest, $poNumber, $rrnumber) {
                $firstAssetRequest = $assetRequest->first();
                if ($firstAssetRequest) {
                    $activity->subject_id = $firstAssetRequest->transaction_number;
                }
            })
            ->log($poNumber === null ? 'PO Number was removed by ' . $user->employee_id . '.' :
                'PO Number ' . $poNumber . ' has been added by ' . $user->employee_id . '.');
    }

    private function composeLogPropertiesPo($assetRequest, $poNumber = null, $rrnumber = null): array
    {
        $requestor = $assetRequest->requestor;
        return [
            'requestor' => [
                'id' => $requestor->id,
                'firstname' => $requestor->firstname,
                'lastname' => $requestor->lastname,
                'employee_id' => $requestor->employee_id,
            ],
            'remaining_to_po' => $this->calculateRemainingQuantity($assetRequest->transaction_number),
            'po_number' => $poNumber ?? null,
            'rr_number' => $rrnumber ?? null,
            'remarks' => $assetRequest->remarks ?? null,
        ];
    }

    private function calculateRemainingQuantity($transactionNumber)
    {

        $items = AssetRequest::where('transaction_number', $transactionNumber)->get();
        $remainingQuantities = $items->map(function ($item) {
            $remaining = $item->quantity - $item->quantity_delivered;
            $remaining = $remaining . "/" . $item->quantity;
            return $remaining;
        });

        $remainingQuantities = $remainingQuantities->reduce(function ($carry, $item) {
            $carry = explode("/", $carry);
            $item = explode("/", $item);
            $carry[0] = $carry[0] + $item[0];
            $carry[1] = $carry[1] + $item[1];
            return $carry[0] . "/" . $carry[1];
        }, "0/0");
        dd($remainingQuantities);

        return $remainingQuantities;
    }
}
