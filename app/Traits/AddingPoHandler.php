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
                $firstAssetRequest = $assetRequest;
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
            'remarks' => null,
        ];
    }

    public function calculateRemainingQuantity($transactionNumber, $forTimeline = false)
    {

        $items = AssetRequest::where('transaction_number', $transactionNumber)->get();
        $remainingQuantities = $items->map(function ($item) {
            $remaining = $item->quantity - $item->quantity_delivered;
            if ($forTimeline = false) {
                $remaining = $remaining . "/" . $item->quantity;
            }

            return $remaining;
        });
        if ($forTimeline = false) {
            $remainingQuantities = $remainingQuantities->reduce(function ($carry, $item) {
                $carry = explode("/", $carry);
                $item = explode("/", $item);
                $carry[0] = $carry[0] + $item[0];
                $carry[1] = $carry[1] + $item[1];
                return $carry[0] . "/" . $carry[1];
            }, "0/0");
        } else {
            $remainingQuantities = $remainingQuantities->reduce(function ($carry, $item) {
                return $carry + $item;
            }, 0);
        }

        return $remainingQuantities;
    }

    public function updatePoAssetRequest($assetRequest, $request)
    {
        $assetRequest->update([
            'po_number' => $request->po_number,
            'rr_number' => $request->rr_number,
            'supplier_id' => $request->supplier_id,
            'delivery_date' => $request->delivery_date,
            'quantity_delivered' => $assetRequest->quantity_delivered + $request->quantity_delivered,
            'unit_price' => $request->unit_price,
        ]);
    }

    public function createNewAssetRequests($assetRequest)
    {
        foreach (range(1, $assetRequest->quantity) as $index) {
            $newAssetRequest = $assetRequest->replicate();
            $newAssetRequest->quantity = 1;
            $newAssetRequest->quantity_delivered = 1;
            $newAssetRequest->save();

            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];
            foreach ($fileKeys as $fileKey) {
                $media = $assetRequest->getMedia($fileKey);
                foreach ($media as $file) {
                    $file->copy($newAssetRequest, $fileKey);
                }
            }
        }
        $assetRequest->update([
            'quantity' => 1,
            'quantity_delivered' => 1,
        ]);
    }
}
