<?php

namespace App\Traits;

use App\Models\AssetApproval;
use App\Models\AssetRequest;
use Illuminate\Pagination\LengthAwarePaginator;

trait AssetRequestHandler
{
    public function getAssetRequest($field, $value, $singleResult = true)
    {
        $query = AssetRequest::where($field, $value)
            ->whereIn('status', ['Pending For 1st Approval', 'Denied']);

        return $singleResult ? $query->first() : $query->get();
    }
    public function getAssetRequestByTransactionNumber($transactionNumber)
    {
        return AssetRequest::where('transaction_number', $transactionNumber)
            ->whereIn('status', ['Pending For 1st Approval', 'Denied'])
            ->get();
    }
    public function updateAssetRequest($assetRequest, $request)
    {
        return $assetRequest->update([
            'type_of_request_id' => $request->type_of_request_id,
            'attachment_type' => $request->attachment_type,
            'accountability' => $request->accountability,
            'accountable' => $request->accountable ?? null,
            'asset_description' => $request->asset_description,
            'asset_specification' => $request->asset_specification ?? null,
            'cellphone_number' => $request->cellphone_number ?? null,
            'brand' => $request->brand ?? null,
            'quantity' => $request->quantity,
        ]);
    }
    public function handleMediaAttachments($assetRequest, $request)
    {
        $collections = [
            'letter_of_request',
            'quotation',
            'specification_form',
            'tool_of_trade',
            'other_attachments'
        ];

        foreach ($collections as $collection) {
            if (isset($request->$collection)) {
                $assetRequest->clearMediaCollection($collection);
                $assetRequest->addMedia($request->$collection)->toMediaCollection($collection);
            } else {
                $assetRequest->clearMediaCollection($collection);
            }
        }
    }

    /**
     * This is for the asset request index page.
     * Handles asset requests.
     *
     * @param Request $request The request object.
     * @return LengthAwarePaginator|array The paginated asset requests or array of asset requests.
     */
    public function transformIndexAssetRequest($request)
    {
        $perPage = $request->input('per_page', null);

        $requesterId = auth('sanctum')->user()->id;

        $assetRequest = AssetRequest::where('requester_id', $requesterId)->useFilters()->get()->groupBy('transaction_number')->map(function ($assetRequestCollection) {
            $assetRequest = $assetRequestCollection->first();
            return [
                'id' => $assetRequest->transaction_number,
                'transaction_number' => $assetRequest->transaction_number,
                'requestor' => [
                    'id' => $assetRequest->requestor->id,
                    'username' => $assetRequest->requestor->username,
                    'employee_id' => $assetRequest->requestor->employee_id,
                    'firstname' => $assetRequest->requestor->firstname,
                    'lastname' => $assetRequest->requestor->lastname,
                ],
                'quantity_of_po' => $assetRequestCollection->count(),
                'date_requested' => $assetRequest->created_at,
                'status' => $assetRequest->status,
            ];
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

    /**
     * This is for the asset request show page.
     *
     * */
    public function transformShowAssetRequest($assetRequest)
    {
        return $assetRequest->transform(function ($ar) {
            $letterOfRequestMedia = $ar->getMedia('letter_of_request')->first();
            $quotationMedia = $ar->getMedia('quotation')->first();
            $specificationFormMedia = $ar->getMedia('specification_form')->first();
            $toolOfTradeMedia = $ar->getMedia('tool_of_trade')->first();
            $otherAttachmentsMedia = $ar->getMedia('other_attachments')->first();

            return [
                'id' => $ar->id,
                'status' => $ar->status,
                'transaction_number' => $ar->transaction_number,
                'reference_number' => $ar->reference_number,
                'pr_number' => $ar->pr_number,
                'po_number' => $ar->po_number,
                'attachment_type' => $ar->attachment_type,
                'remarks' => $ar->remarks,
                'accountability' => $ar->accountability,
                'accountable' => $ar->accountable ?? '-',
                'asset_description' => $ar->asset_description,
                'asset_specification' => $ar->asset_specification ?? '-',
                'cellphone_number' => $ar->cellphone_number ?? '-',
                'brand' => $ar->brand ?? '-',
                'quantity' => $ar->quantity,
                'requestor' => [
                    'id' => $ar->requestor->id,
                    'username' => $ar->requestor->username,
                    'employee_id' => $ar->requestor->employee_id,
                    'firstname' => $ar->requestor->firstname,
                    'lastname' => $ar->requestor->lastname,
                ],
                'type_of_request' => [
                    'id' => $ar->typeOfRequest->id,
                    'type_of_request_name' => $ar->typeOfRequest->type_of_request_name,
                ],
                'charged_department' => [
                    'id' => $ar->chargedDepartment->id,
                    'charged_department_name' => $ar->chargedDepartment->department_name,
                ],
                'subunit' => [
                    'id' => $ar->subunit->id,
                    'subunit_name' => $ar->subunit->subunit_name,
                ],
                'attachments' => [
                    'letter_of_request' => [
                        'id' => $letterOfRequestMedia ? $letterOfRequestMedia->id : '-',
                        'file_name' => $letterOfRequestMedia ? $letterOfRequestMedia->file_name : '-',
                        'file_path' => $letterOfRequestMedia ? $letterOfRequestMedia->getPath() : '-',
                        'file_url' => $letterOfRequestMedia ? $letterOfRequestMedia->getUrl() : '-',
                    ],
                    'quotation' => [
                        'id' => $quotationMedia ? $quotationMedia->id : '-',
                        'file_name' => $quotationMedia ? $quotationMedia->file_name : '-',
                        'file_path' => $quotationMedia ? $quotationMedia->getPath() : '-',
                        'file_url' => $quotationMedia ? $quotationMedia->getUrl() : '-',
                    ],
                    'specification_form' => [
                        'id' => $specificationFormMedia ? $specificationFormMedia->id : '-',
                        'file_name' => $specificationFormMedia ? $specificationFormMedia->file_name : '-',
                        'file_path' => $specificationFormMedia ? $specificationFormMedia->getPath() : '-',
                        'file_url' => $specificationFormMedia ? $specificationFormMedia->getUrl() : '-',
                    ],
                    'tool_of_trade' => [
                        'id' => $toolOfTradeMedia ? $toolOfTradeMedia->id : '-',
                        'file_name' => $toolOfTradeMedia ? $toolOfTradeMedia->file_name : '-',
                        'file_path' => $toolOfTradeMedia ? $toolOfTradeMedia->getPath() : '-',
                        'file_url' => $toolOfTradeMedia ? $toolOfTradeMedia->getUrl() : '-',
                    ],
                    'other_attachments' => [
                        'id' => $otherAttachmentsMedia ? $otherAttachmentsMedia->id : '-',
                        'file_name' => $otherAttachmentsMedia ? $otherAttachmentsMedia->file_name : '-',
                        'file_path' => $otherAttachmentsMedia ? $otherAttachmentsMedia->getPath() : '-',
                        'file_url' => $otherAttachmentsMedia ? $otherAttachmentsMedia->getUrl() : '-',
                    ],
                ]
            ];
        });
    }

    public function voidRequestItem($referenceNumber)
    {
        $assetRequest = $this->getAssetRequest('reference_number',$referenceNumber);

        if (!$assetRequest) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        if ($this->requestCount($assetRequest->transaction_number) == 1) {
            $assetRequest->update([
                'status' => 'Void'
            ]);

            $this->updateToVoid($assetRequest->transaction_number, 'Void');

            $assetRequest->delete();

            return $this->responseSuccess('Asset Request voided Successfully');
        }

        $assetRequest->update([
            'status' => 'Void'
        ]);
        $assetRequest->delete();

        return $this->responseSuccess('Asset Request voided Successfully');
    }

    public function voidAssetRequest($transactionNumber){

       $assetRequest = $this->getAssetRequest('transaction_number', $transactionNumber, false);

        if ($assetRequest->isEmpty()) {
            return $this->responseUnprocessable('Asset Request not found.');
        }
        $this->updateToVoid($transactionNumber, 'Void');
        foreach($assetRequest as $ar){
            $ar->update([
                'status' => 'Void'
            ]);
            $ar->delete();
        }
        return $this->responseSuccess('Asset Request voided Successfully');
    }

    public function requestCount($transactionNumber){
        $requestCount = AssetRequest::where('transaction_number', $transactionNumber)->count();
        return $requestCount;
    }

    public function updateToVoid($transactionNumber, $status){

        return AssetApproval::where('transaction_number', $transactionNumber)
            ->update(['status' => $status]);
    }
}
