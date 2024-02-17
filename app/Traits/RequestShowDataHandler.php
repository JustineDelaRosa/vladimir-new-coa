<?php

namespace App\Traits;

use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\DepartmentUnitApprovers;
use App\Models\FixedAsset;
use App\Repositories\CalculationRepository;
use App\Repositories\FixedAssetRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait RequestShowDataHandler
{

    use ApiResponse;
    private function responseData($data)
    {
        if ($data instanceof Collection) {
            return $this->collectionData($data);
        } elseif ($data instanceof LengthAwarePaginator) {
            $data->getCollection()->transform(function ($item) {
                return $this->transformItem($item);
            });
            return $data;
        } else {
            return $this->nonCollectionData($data);
        }
    }

    private function collectionData($data)
    {
        return $data->transform(function ($ar) {
            return $this->response($ar);
        });
    }

    private function nonCollectionData($data)
    {
        return $data->getCollection()->transform(function ($ar) {
            return $this->response($ar);
        });
    }

    private function transformItem($ar): array
    {
        return $this->response($ar);
    }

    private function response($ar): array
    {
        $letterOfRequestMedia = $ar->getMedia('letter_of_request')->first();
        $quotationMedia = $ar->getMedia('quotation')->first();
        $specificationFormMedia = $ar->getMedia('specification_form')->first();
        $toolOfTradeMedia = $ar->getMedia('tool_of_trade')->first();
        $otherAttachmentsMedia = $ar->getMedia('other_attachments')->first();
        //sum all the total delivered of the asset request with the same transaction number
        $totalDelivered = AssetRequest::where('transaction_number', $ar->transaction_number)->sum('quantity_delivered');
        $totalOrdered = AssetRequest::where('transaction_number', $ar->transaction_number)->sum('quantity');
        $isEqual = $totalDelivered == $totalOrdered;

        $userId = auth()->user()->id;
        $approversId = Approvers::where('approver_id', $userId)->first();
        $approverId = $approversId ? $approversId->id : 0;

        $isUserLastApprover = AssetApproval::where('transaction_number', $ar->transaction_number)
            ->where('status', 'Approved')
            ->max('layer');
        $approver = DepartmentUnitApprovers::where('subunit_id', $ar->subunit_id)
            ->where('approver_id', $approverId)->first();

        $isUserLastApprover = $approver ? $isUserLastApprover == $approver->layer : false;

        return [
            'is_trashed' => $ar->trashed() ? 1 : 0,
            'can_edit' => ($ar->status == 'Returned' || $ar->status == 'For Approval of Approver 1') || ($isUserLastApprover) ? 1 : 0,
            'can_resubmit' => $ar->status == 'Returned' ? 1 : 0,
            'asset_approval_id' => $ar->assetApproval->first(function ($approval) {
                    return $approval->status == 'For Approval';
                })->id ?? '',
            'id' => $ar->id,
            'status' => $ar->status,
            'transaction_number' => $ar->transaction_number,
            'reference_number' => $ar->reference_number,
            'pr_number' => $ar->pr_number,
            'po_number' => $ar->po_number,
            'attachment_type' => $ar->attachment_type,
            'is_addcost' => $ar->is_addcost ?? 0,
            'remarks' => $ar->remarks ?? '',
            'accountability' => $ar->accountability,
            'accountable' => $ar->accountable ?? '-',
            'additional_info' => $ar->additional_info ?? '-',
            'acquisition_details' => $ar->acquisition_details ?? '-',
            'asset_description' => $ar->asset_description,
            'asset_specification' => $ar->asset_specification ?? '-',
            'cellphone_number' => $ar->cellphone_number ?? '-',
            'brand' => $ar->brand ?? '-',
            'date_needed' => $ar->date_needed ?? '-',
            'quantity' => $ar->quantity,
            'ordered' => $ar->quantity,
            'delivered' => $ar->quantity_delivered ?? '-',
            'remaining' => $ar->quantity - $ar->quantity_delivered ?? '-',
            'is_equal' => $isEqual,
//            'fixed_asset' => [
//                'id' => $ar->fixedAsset->id ?? '-',
//                'vladimir_tag_number' => $ar->fixedAsset->vladimir_tag_number ?? '-',
//            ],
            'fixed_asset' => $ar->fixedAsset ? $this->transformSingleFixedAsset($ar->fixedAsset) : '-',
            'requestor' => [
                'id' => $ar->requestor->id,
                'username' => $ar->requestor->username,
                'employee_id' => $ar->requestor->employee_id,
                'firstname' => $ar->requestor->firstname,
                'lastname' => $ar->requestor->lastname,
            ],
            'type_of_request' => [
                'id' => $ar->typeOfRequest->id,
                'type_of_request_name' => $ar->typeOfRequest->type_of_request_name ?? '-',
            ],
            'company' => [
                'id' => $ar->company->id,
                'company_code' => $ar->company->company_code,
                'company_name' => $ar->company->company_name,
            ],
            'business_unit' => [
                'id' => $ar->businessUnit->id ?? '-',
                'business_unit_code' => $ar->businessUnit->company_code ?? '-',
                'business_unit_name' => $ar->businessUnit->company_name ?? '-',
            ],
            'department' => [
                'id' => $ar->department->id,
                'department_code' => $ar->department->department_code,
                'department_name' => $ar->department->department_name,
                'sync_id' => $ar->department->sync_id,
            ],
            'subunit' => [
                'id' => $ar->subunit->id,
                'subunit_code' => $ar->subunit->sub_unit_code,
                'subunit_name' => $ar->subunit->sub_unit_name,
            ],
            'location' => [
                'id' => $ar->location->id,
                'location_code' => $ar->location->location_code,
                'location_name' => $ar->location->location_name,
            ],
            'account_title' => [
                'id' => $ar->accountTitle->id,
                'account_title_code' => $ar->accountTitle->account_title_code,
                'account_title_name' => $ar->accountTitle->account_title_name,
            ],
            'supplier' => [
                'id' => $ar->supplier->id ?? '-',
                'supplier_code' => $ar->supplier->supplier_code ?? '-',
                'supplier_name' => $ar->supplier->supplier_name ?? '-',
            ],
            'attachments' => [
                //TODO: This is the viewing if the attachments are multiple
                /*
                 *
                  $letterOfRequestMedia ? collect($letterOfRequestMedia)->map(function ($media) {
                      return [
                          'id' => $media->id,
                          'file_name' => $media->file_name,
                          'file_path' => $media->getPath(),
                          'file_url' => $media->getUrl(),
                      ];
                  }) : '-',
                */
                'letter_of_request' => $letterOfRequestMedia ? [
                    'id' => $letterOfRequestMedia->id,
                    'file_name' => $letterOfRequestMedia->file_name,
                    'file_path' => $letterOfRequestMedia->getPath(),
                    'file_url' => $letterOfRequestMedia->getUrl(),
                ] : null,
                'quotation' => $quotationMedia ? [
                    'id' => $quotationMedia->id,
                    'file_name' => $quotationMedia->file_name,
                    'file_path' => $quotationMedia->getPath(),
                    'file_url' => $quotationMedia->getUrl(),
                ] : null,
                'specification_form' => $specificationFormMedia ? [
                    'id' => $specificationFormMedia->id,
                    'file_name' => $specificationFormMedia->file_name,
                    'file_path' => $specificationFormMedia->getPath(),
                    'file_url' => $specificationFormMedia->getUrl(),
                ] : null,
                'tool_of_trade' => $toolOfTradeMedia ? [
                    'id' => $toolOfTradeMedia->id,
                    'file_name' => $toolOfTradeMedia->file_name,
                    'file_path' => $toolOfTradeMedia->getPath(),
                    'file_url' => $toolOfTradeMedia->getUrl(),
                ] : null,
                'other_attachments' => $otherAttachmentsMedia ? [
                    'id' => $otherAttachmentsMedia->id,
                    'file_name' => $otherAttachmentsMedia->file_name,
                    'file_path' => $otherAttachmentsMedia->getPath(),
                    'file_url' => $otherAttachmentsMedia->getUrl(),
                ] : null,
            ]

        ];


    }

    private function transformSingleFixedAsset($fixed_asset): array
    {

        $fixed_asset->additional_cost_count = $fixed_asset->additionalCost ? $fixed_asset->additionalCost->count() : 0;
        return [
            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->first_name ?? '-',
                'last_name' => $fixed_asset->requestor->last_name ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'po_number' => $fixed_asset->po_number ?? '-',
            'rr_number' => $fixed_asset->rr_number ?? '-',
            'warehouse_number' => [
                'id' => $fixed_asset->warehouseNumber->id ?? '-',
                'warehouse_number' => $fixed_asset->warehouseNumber->warehouse_number ?? '-',
            ],
            'from_request' => $fixed_asset->from_request ?? '-',
            'can_release' => $fixed_asset->can_release ?? '-',
            'capex' => [
                'id' => $fixed_asset->capex->id ?? '-',
                'capex' => $fixed_asset->capex->capex ?? '-',
                'project_name' => $fixed_asset->capex->project_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $fixed_asset->subCapex->id ?? '-',
                'sub_capex' => $fixed_asset->subCapex->sub_capex ?? '-',
                'sub_project' => $fixed_asset->subCapex->sub_project ?? '-',
            ],
            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
            'tag_number' => $fixed_asset->tag_number ?? '-',
            'tag_number_old' => $fixed_asset->tag_number_old ?? '-',
            'asset_description' => $fixed_asset->asset_description ?? '-',
            'type_of_request' => [
                'id' => $fixed_asset->typeOfRequest->id ?? '-',
                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $fixed_asset->asset_specification ?? '-',
            'accountability' => $fixed_asset->accountability ?? '-',
            'accountable' => $fixed_asset->accountable ?? '-',
            'cellphone_number' => $fixed_asset->cellphone_number ?? '-',
            'brand' => $fixed_asset->brand ?? '-',
            'supplier' => [
                'id' => $fixed_asset->supplier->id ?? '-',
                'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
                'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
            ],
            'division' => [
                'id' => $fixed_asset->department->division->id ?? '-',
                'division_name' => $fixed_asset->department->division->division_name ?? '-',
            ],
            'major_category' => [
                'id' => $fixed_asset->majorCategory->id ?? '-',
                'major_category_name' => $fixed_asset->majorCategory->major_category_name ?? '-',
            ],
            'minor_category' => [
                'id' => $fixed_asset->minorCategory->id ?? '-',
                'minor_category_name' => $fixed_asset->minorCategory->minor_category_name ?? '-',
            ],
            'est_useful_life' => $fixed_asset->majorCategory->est_useful_life ?? '-',
            'voucher' => $fixed_asset->voucher ?? '-',
            'voucher_date' => $fixed_asset->voucher_date ?? '-',
            'receipt' => $fixed_asset->receipt ?? '-',
            'quantity' => $fixed_asset->quantity ?? '-',
            'depreciation_method' => $fixed_asset->depreciation_method ?? '-',
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date ?? '-',
            'acquisition_cost' => $fixed_asset->acquisition_cost ?? '-',
            'scrap_value' => $fixed_asset->formula->scrap_value ?? '-',
            'depreciable_basis' => $fixed_asset->formula->depreciable_basis ?? '-',
            'accumulated_cost' => $fixed_asset->formula->accumulated_cost ?? '-',
            'asset_status' => [
                'id' => $fixed_asset->assetStatus->id ?? '-',
                'asset_status_name' => $fixed_asset->assetStatus->asset_status_name ?? '-',
            ],
            'cycle_count_status' => [
                'id' => $fixed_asset->cycleCountStatus->id ?? '-',
                'cycle_count_status_name' => $fixed_asset->cycleCountStatus->cycle_count_status_name ?? '-',
            ],
            'depreciation_status' => [
                'id' => $fixed_asset->depreciationStatus->id ?? '-',
                'depreciation_status_name' => $fixed_asset->depreciationStatus->depreciation_status_name ?? '-',
            ],
            'movement_status' => [
                'id' => $fixed_asset->movementStatus->id ?? '-',
                'movement_status_name' => $fixed_asset->movementStatus->movement_status_name ?? '-',
            ],
            'is_additional_cost' => $fixed_asset->is_additional_cost ?? '-',
            'is_old_asset' => $fixed_asset->is_old_asset ?? '-',
            'status' => $fixed_asset->is_active ?? '-',
            'care_of' => $fixed_asset->care_of ?? '-',
            'months_depreciated' => $fixed_asset->formula->months_depreciated ?? '-',
            'end_depreciation' => $fixed_asset->formula->end_depreciation ?? '-',
            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year ?? '-',
            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month ?? '-',
            'remaining_book_value' => $fixed_asset->formula->remaining_book_value ?? '-',
            'release_date' => $fixed_asset->formula->release_date ?? '-',
            'start_depreciation' => $fixed_asset->formula->start_depreciation ?? '-',
            'company' => [
                'id' => $fixed_asset->department->company->id ?? '-',
                'company_code' => $fixed_asset->department->company->company_code ?? '-',
                'company_name' => $fixed_asset->department->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->department->businessUnit->id ?? '-',
                'business_unit_code' => $fixed_asset->department->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->department->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'charged_department' => [
                'id' => $additional_cost->department->id ?? '-',
                'charged_department_code' => $additional_cost->department->department_code ?? '-',
                'charged_department_name' => $additional_cost->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->location->id ?? '-',
                'location_code' => $fixed_asset->location->location_code ?? '-',
                'location_name' => $fixed_asset->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $fixed_asset->accountTitle->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->account_title_name ?? '-',
            ],
            'remarks' => $fixed_asset->remarks,
            'print_count' => $fixed_asset->print_count,
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $fixed_asset->last_printed,
            'tagging' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'additional_cost' => isset($fixed_asset->additionalCost) ? $fixed_asset->additionalCost->map(function ($additional_cost) {
                return [
                    'id' => $additional_cost->id ?? '-',
                    'requestor' => [
                        'id' => $additional_cost->requestor->id ?? '-',
                        'username' => $additional_cost->requestor->username ?? '-',
                        'first_name' => $additional_cost->requestor->first_name ?? '-',
                        'last_name' => $additional_cost->requestor->last_name ?? '-',
                        'employee_id' => $additional_cost->requestor->employee_id ?? '-',
                    ],
                    'pr_number' => $additional_cost->pr_number ?? '-',
                    'po_number' => $additional_cost->po_number ?? '-',
                    'rr_number' => $additional_cost->rr_number ?? '-',
                    'warehouse_number' => [
                        'id' => $additional_cost->warehouseNumber->id ?? '-',
                        'warehouse_number' => $additional_cost->warehouseNumber->warehouse_number ?? '-',
                    ],
                    'from_request' => $additional_cost->from_request ?? '-',
                    'can_release' => $additional_cost->can_release ?? '-',
                    'add_cost_sequence' => $additional_cost->add_cost_sequence ?? '-',
                    'asset_description' => $additional_cost->asset_description ?? '-',
                    'type_of_request' => [
                        'id' => $additional_cost->typeOfRequest->id ?? '-',
                        'type_of_request_name' => $additional_cost->typeOfRequest->type_of_request_name ?? '-',
                    ],
                    'asset_specification' => $additional_cost->asset_specification ?? '-',
                    'accountability' => $additional_cost->accountability ?? '-',
                    'accountable' => $additional_cost->accountable ?? '-',
                    'cellphone_number' => $additional_cost->cellphone_number ?? '-',
                    'brand' => $additional_cost->brand ?? '-',
                    'supplier' => [
                        'id' => $fixed_asset->supplier->id ?? '-',
                        'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
                        'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
                    ],
                    'division' => [
                        'id' => $additional_cost->department->division->id ?? '-',
                        'division_name' => $additional_cost->department->division->division_name ?? '-',
                    ],
                    'major_category' => [
                        'id' => $additional_cost->majorCategory->id ?? '-',
                        'major_category_name' => $additional_cost->majorCategory->major_category_name ?? '-',
                    ],
                    'minor_category' => [
                        'id' => $additional_cost->minorCategory->id ?? '-',
                        'minor_category_name' => $additional_cost->minorCategory->minor_category_name ?? '-',
                    ],
                    'est_useful_life' => $additional_cost->majorCategory->est_useful_life ?? '-',
                    'voucher' => $additional_cost->voucher ?? '-',
                    'voucher_date' => $additional_cost->voucher_date ?? '-',
                    'receipt' => $additional_cost->receipt ?? '-',
                    'quantity' => $additional_cost->quantity ?? '-',
                    'depreciation_method' => $additional_cost->depreciation_method ?? '-',
                    //                    'salvage_value' => $additional_cost->salvage_value,
                    'acquisition_date' => $additional_cost->acquisition_date ?? '-',
                    'acquisition_cost' => $additional_cost->acquisition_cost ?? '-',
                    'scrap_value' => $additional_cost->formula->scrap_value ?? '-',
                    'depreciable_basis' => $additional_cost->formula->depreciable_basis ?? '-',
                    'accumulated_cost' => $additional_cost->formula->accumulated_cost ?? '-',
                    'asset_status' => [
                        'id' => $additional_cost->assetStatus->id ?? '-',
                        'asset_status_name' => $additional_cost->assetStatus->asset_status_name ?? '-',
                    ],
                    'cycle_count_status' => [
                        'id' => $additional_cost->cycleCountStatus->id ?? '-',
                        'cycle_count_status_name' => $additional_cost->cycleCountStatus->cycle_count_status_name ?? '-',
                    ],
                    'depreciation_status' => [
                        'id' => $additional_cost->depreciationStatus->id ?? '-',
                        'depreciation_status_name' => $additional_cost->depreciationStatus->depreciation_status_name ?? '-',
                    ],
                    'movement_status' => [
                        'id' => $additional_cost->movementStatus->id ?? '-',
                        'movement_status_name' => $additional_cost->movementStatus->movement_status_name ?? '-',
                    ],
                    'is_additional_cost' => $additional_cost->is_additional_cost ?? '-',
                    'status' => $additional_cost->is_active ?? '-',
                    'care_of' => $additional_cost->care_of ?? '-',
                    'months_depreciated' => $additional_cost->formula->months_depreciated ?? '-',
                    'end_depreciation' => $additional_cost->formula->end_depreciation ?? '-',
                    'depreciation_per_year' => $additional_cost->formula->depreciation_per_year ?? '-',
                    'depreciation_per_month' => $additional_cost->formula->depreciation_per_month ?? '-',
                    'remaining_book_value' => $additional_cost->formula->remaining_book_value ?? '-',
                    'release_date' => $additional_cost->formula->release_date ?? '-',
                    'start_depreciation' => $additional_cost->formula->start_depreciation ?? '-',
                    'company' => [
                        'id' => $additional_cost->department->company->id ?? '-',
                        'company_code' => $additional_cost->department->company->company_code ?? '-',
                        'company_name' => $additional_cost->department->company->company_name ?? '-',
                    ],
                    'business_unit' => [
                        'id' => $fixed_asset->department->businessUnit->id ?? '-',
                        'business_unit_code' => $fixed_asset->department->businessUnit->business_unit_code ?? '-',
                        'business_unit_name' => $fixed_asset->department->businessUnit->business_unit_name ?? '-',
                    ],
                    'department' => [
                        'id' => $additional_cost->department->id ?? '-',
                        'department_code' => $additional_cost->department->department_code ?? '-',
                        'department_name' => $additional_cost->department->department_name ?? '-',
                    ],
                    'charged_department' => [
                        'id' => $additional_cost->department->id ?? '-',
                        'charged_department_code' => $additional_cost->department->department_code ?? '-',
                        'charged_department_name' => $additional_cost->department->department_name ?? '-',
                    ],
                    'location' => [
                        'id' => $additional_cost->location->id ?? '-',
                        'location_code' => $additional_cost->location->location_code ?? '-',
                        'location_name' => $additional_cost->location->location_name ?? '-',
                    ],
                    'account_title' => [
                        'id' => $additional_cost->accountTitle->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->account_title_name ?? '-',
                    ],
                    'remarks' => $additional_cost->remarks ?? '-',
                ];
            }) : [],
        ];
    }
}
