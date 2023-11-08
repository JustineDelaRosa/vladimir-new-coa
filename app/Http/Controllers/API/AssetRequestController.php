<?php

namespace App\Http\Controllers\API;

use App\Models\ApproverLayer;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\DepartmentUnitApprovers;
use App\Models\RoleManagement;
use App\Models\SubCapex;
use App\Repositories\ApprovedRequestRepository;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

use App\Http\Requests\AssetRequest\CreateAssetRequestRequest;
use App\Http\Requests\AssetRequest\UpdateAssetRequestRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class AssetRequestController extends Controller
{
    use ApiResponse;

    private $approveRequestRepository;

    public function __construct(ApprovedRequestRepository $approveRequestRepository)
    {
        $this->approveRequestRepository = $approveRequestRepository;
    }

    public function index(Request $request)
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

//    public function store(CreateAssetRequestRequest $request)
//    {
//        $userRequest = $request->userRequest;
//        $requesterId = auth('sanctum')->user()->id;
//
//        foreach ($userRequest as $request) {
//            $assetRequest = AssetRequest::create([
//                'requester_id' => $requesterId,
//                'transaction_number' => 001,
//                'reference_number' => 001,
//                'type_of_request_id' => $request['type_of_request_id'],
//                'charged_department_id' => $request['charged_department_id'],
//                'subunit_id' => $request['subunit_id'],
//                'accountability' => $request['accountability'],
//                'accountable' => $request['accountable'] ?? null,
//                'asset_description' => $request['asset_description'],
//                'asset_specification' => $request['asset_specification'] ?? null,
//                'cellphone_number' => $request['cellphone_number'] ?? null,
//                'brand' => $request['brand'] ?? null,
//                'quantity' => $request['quantity'],
//                'letter_of_request' => $request['letter_of_request']['file_name']
//                /*'quotation' => $request['quotation']->clientOriginalName(),
//                'specification_form' => $request['specification_form']->clientOriginalName(),
//                'tool_of_trade' => $request['tool_of_trade']->clientOriginalName(),
//                'other_attachments' => $request['other_attachments']->clientOriginalName(),*/
//            ]);
//
//            $assetRequest->addMediaFromRequest('letter_of_request')->toMediaCollection('download');
//        }
//
//
//        return $this->responseCreated('AssetRequest created successfully');
//    }

//    public function store(CreateAssetRequestRequest $request)
//    {
//        $userRequest = $request->userRequest;
//        $requesterId = auth('sanctum')->user()->id;
//
//        foreach ($userRequest as $request) {
//            $assetRequest = AssetRequest::create([
//                'requester_id' => $requesterId,
//                'transaction_number' => 001,
//                'reference_number' => 001,
//                'type_of_request_id' => $request['type_of_request_id'],
//                'charged_department_id' => $request['charged_department_id'],
//                'subunit_id' => $request['subunit_id'],
//                'accountability' => $request['accountability'],
//                'accountable' => $request['accountable'] ?? null,
//                'asset_description' => $request['asset_description'],
//                'asset_specification' => $request['asset_specification'] ?? null,
//                'cellphone_number' => $request['cellphone_number'] ?? null,
//                'brand' => $request['brand'] ?? null,
//                'quantity' => $request['quantity'],
//            ]);
//            // If you want to store the original file name
//            $assetRequest->letter_of_request = $request['letter_of_request']->getClientOriginalName();
//            $assetRequest->quotation = $request['quotation']->getClientOriginalName();
//            $assetRequest->specification_form = $request['specification_form']->getClientOriginalName();
//            $assetRequest->tool_of_trade = $request['tool_of_trade']->getClientOriginalName();
//            $assetRequest->other_attachments = $request['other_attachments']->getClientOriginalName();
//            $assetRequest->save();
//
//            $assetRequest->addMedia($request['letter_of_request'])->toMediaCollection('letter_of_request');
//            $assetRequest->addMedia($request['quotation'])->toMediaCollection('quotation');
//            $assetRequest->addMedia($request['specification_form'])->toMediaCollection('specification_form');
//            $assetRequest->addMedia($request['tool_of_trade'])->toMediaCollection('tool_of_trade');
//            $assetRequest->addMedia($request['other_attachments'])->toMediaCollection('other_attachments');
//
//
//        }
//
//        return $this->responseCreated('AssetRequest created successfully');
//    }


    public function store(CreateAssetRequestRequest $request)
    {
        $userRequest = $request->userRequest;
        $requesterId = auth('sanctum')->user()->id;

        $lastTransaction = AssetRequest::orderBy('transaction_number', 'desc')->first();
        $transactionNumber = $lastTransaction ? $lastTransaction->transaction_number + 1 : 1;
        $transactionNumber = str_pad($transactionNumber, 4, '0', STR_PAD_LEFT);

        foreach ($userRequest as $request) {
            $assetRequest = AssetRequest::create([
                'requester_id' => $requesterId,
                'transaction_number' => $transactionNumber,
                'reference_number' => (new AssetRequest)->generateReferenceNumber(),
                'type_of_request_id' => $request['type_of_request_id'],
                'attachment_type' => $request['attachment_type'],
                'charged_department_id' => $request['charged_department_id'],
                'subunit_id' => $request['subunit_id'],
                'accountability' => $request['accountability'],
                'accountable' => $request['accountable'] ?? null,
                'asset_description' => $request['asset_description'],
                'asset_specification' => $request['asset_specification'] ?? null,
                'cellphone_number' => $request['cellphone_number'] ?? null,
                'brand' => $request['brand'] ?? null,
                'quantity' => $request['quantity'],
            ]);

            if (isset($request['letter_of_request'])) {
                $assetRequest->addMedia($request['letter_of_request'])->toMediaCollection('letter_of_request');
            }
            if (isset($request['quotation'])) {
                $assetRequest->addMedia($request['quotation'])->toMediaCollection('quotation');
            }
            if (isset($request['specification_form'])) {
                $assetRequest->addMedia($request['specification_form'])->toMediaCollection('specification_form');
            }
            if (isset($request['tool_of_trade'])) {
                $assetRequest->addMedia($request['tool_of_trade'])->toMediaCollection('tool_of_trade');
            }
            if (isset($request['other_attachments'])) {
                $assetRequest->addMedia($request['other_attachments'])->toMediaCollection('other_attachments');
            }
        }

        $departmentUnitApprovers = DepartmentUnitApprovers::where('subunit_id', $userRequest[0]['subunit_id'])
            ->orderBy('layer', 'asc')
            ->get();

        $firstLayerFlag = true;
        foreach ($departmentUnitApprovers as $departmentUnitApprover) {
            $approver_id = $departmentUnitApprover->approver_id;
            $layer = $departmentUnitApprover->layer;
            $status = $layer == 1 ? 'pending' : null;
            AssetApproval::create([
                'transaction_number' => $assetRequest->transaction_number,
                'approver_id' => $approver_id,
                'requester_id' => $requesterId,
                'layer' => $layer,
                'status' => $status,
            ]);
            $firstLayerFlag = false;
        }


        return $this->responseCreated('AssetRequest created successfully');
    }

    public
    function show($transactionNumber)
    {
        //For Specific Viewing of Asset Request with the same transaction number
        $requestorId = auth('sanctum')->user()->id;

        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('requester_id', $requestorId)
            ->get();

        $assetRequest->transform(function ($ar) {
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

        return $assetRequest;
//        return $this->responseSuccess(null, [
//            'id' => $assetRequest->id,
//            'status' => $assetRequest->status,
//            'current_approver' => $assetRequest->currentApprover->first()->approver->user ?? null,
//            'requester' => [
//                'id' => $assetRequest->requester->id,
//                'username' => $assetRequest->requester->username,
//                'employee_id' => $assetRequest->requester->employee_id,
//                'firstname' => $assetRequest->requester->firstname,
//                'lastname' => $assetRequest->requester->lastname,
//            ],
//            'type_of_request' => [
//                'id' => $assetRequest->typeOfRequest->id,
//                'type_of_request_name' => $assetRequest->typeOfRequest->type_of_request_name,
//            ],
//            'capex' => [
//                'id' => $assetRequest->capex->id ?? '-',
//                'capex_name' => $assetRequest->capex->capex_name ?? '-',
//            ],
//            'sub_capex' => [
//                'id' => $assetRequest->subCapex->id ?? '-',
//                'sub_capex_name' => $assetRequest->subCapex->sub_capex_name ?? '-',
//            ],
//            'asset_description' => $assetRequest->asset_description,
//            'asset_specification' => $assetRequest->asset_specification ?? '-',
//            'accountability' => $assetRequest->accountability,
//            'accountable' => $assetRequest->accountable ?? '-',
//            'cellphone_number' => $assetRequest->cellphone_number ?? '-',
//            'brand' => $assetRequest->brand ?? '-',
//            'quantity' => $assetRequest->quantity ?? '-',
//        ]);
    }

    public function update(UpdateAssetRequestRequest $request, $referenceNumber): JsonResponse
    {
        $assetRequest = AssetRequest::where('reference_number', $referenceNumber)->first();
        if (!$assetRequest) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        $assetRequest->update([
            'type_of_request_id' => $request['type_of_request_id'],
            /*'charged_department_id' => $request['charged_department_id'],
            'subunit_id' => $request['subunit_id'],*/
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? null,
            'asset_description' => $request['asset_description'],
            'asset_specification' => $request['asset_specification'] ?? null,
            'cellphone_number' => $request['cellphone_number'] ?? null,
            'brand' => $request['brand'] ?? null,
            'quantity' => $request['quantity'],
        ]);


        return $this->responseSuccess('AssetRequest updated Successfully', [
            'id' => $assetRequest->id,
            'status' => $assetRequest->status,
            'requester' => [
                'id' => $assetRequest->requester->id,
                'username' => $assetRequest->requester->username,
                'employee_id' => $assetRequest->requester->employee_id,
                'firstname' => $assetRequest->requester->firstname,
                'lastname' => $assetRequest->requester->lastname,
            ],
            'type_of_request' => [
                'id' => $assetRequest->typeOfRequest->id,
                'type_of_request_name' => $assetRequest->typeOfRequest->type_of_request_name,
            ],
            'capex' => [
                'id' => $assetRequest->capex->id ?? '-',
                'capex_name' => $assetRequest->capex->capex_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $assetRequest->subCapex->id ?? '-',
                'sub_capex_name' => $assetRequest->subCapex->sub_capex_name ?? '-',
            ],
            'asset_description' => $assetRequest->asset_description,
            'asset_specification' => $assetRequest->asset_specification ?? '-',
            'accountability' => $assetRequest->accountability,
            'accountable' => $assetRequest->accountable ?? '-',
            'cellphone_number' => $assetRequest->cellphone_number ?? '-',
            'brand' => $assetRequest->brand ?? '-',
        ]);
    }

    public
    function destroy(AssetRequest $assetRequest): JsonResponse
    {
        $assetRequest->delete();

        return $this->responseDeleted();
    }

    public
    function resubmitRequest(CreateAssetRequestRequest $request): JsonResponse
    {
        $requestIds = $request->request_id;

        return $this->approveRequestRepository->resubmitRequest($requestIds);
    }
}
