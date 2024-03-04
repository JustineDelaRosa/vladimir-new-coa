<?php

namespace App\Traits;

use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\DepartmentUnitApprovers;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait ItemDetailsHandler
{
    public function responseForAssetRequest($data)
    {
        if ($data instanceof LengthAwarePaginator) {
            $data->getCollection()->transform(function ($item) {
                return $this->transformAssetRequest($item);
            });
            return $data;
        } else if ($data instanceof Collection) {
            $data->transform(function ($item) {
                return $this->transformAssetRequest($item);
            });
            return $data;
        } else {
            return null;
        }
    }

    public function transformAssetRequest($item)
    {
        $letterOfRequestMedia = $item->getMedia('letter_of_request')->first();
        $quotationMedia = $item->getMedia('quotation')->first();
        $specificationFormMedia = $item->getMedia('specification_form')->first();
        $toolOfTradeMedia = $item->getMedia('tool_of_trade')->first();
        $otherAttachmentsMedia = $item->getMedia('other_attachments')->first();
        //sum all the total delivered of the asset request with the same transaction number
        $totalDelivered = AssetRequest::where('transaction_number', $item->transaction_number)->sum('quantity_delivered');
        $totalOrdered = AssetRequest::where('transaction_number', $item->transaction_number)->sum('quantity');
        $isEqual = $totalDelivered == $totalOrdered;

        $deletedQuantity = AssetRequest::onlyTrashed()->where('transaction_number', $item->transaction_number)->where('reference_number', $item->reference_number)->sum('quantity');

        $userId = auth()->user()->id;
        $approversId = Approvers::where('approver_id', $userId)->first();
        $approverId = $approversId ? $approversId->id : 0;

        $isUserLastApprover = AssetApproval::where('transaction_number', $item->transaction_number)
            ->where('status', 'Approved')
            ->max('layer');
        $approver = DepartmentUnitApprovers::where('subunit_id', $item->subunit_id)
            ->where('approver_id', $approverId)->first();

        $isUserLastApprover = $approver ? $isUserLastApprover == $approver->layer : false;
        $totalRemaining = $totalOrdered - $totalDelivered;

        return [
            'is_removed' => $item->trashed() ? 1 : 0,
            //check if the requester_id is equal to deleter_id then the requester deleted it else get the role name of the deleter
            'removed_by' => $item->deleter_id == $item->requester_id ? "Requestor" : ($item->deleter ? $item->deleter->role->role_name : null),
            'can_edit' => ($item->status == 'Returned' || $item->status == 'For Approval of Approver 1') || ($isUserLastApprover) ? 1 : 0,
            'can_resubmit' => $item->status == 'Returned' ? 1 : 0,
            'asset_approval_id' => $item->assetApproval->first(function ($approval) {
                    return $approval->status == 'For Approval';
                })->id ?? '',
            'id' => $item->id,
            'total_remaining' => $totalRemaining,
            'status' => $item->status,
            'transaction_number' => $item->transaction_number,
            'reference_number' => $item->reference_number,
            'pr_number' => $item->pr_number,
            'po_number' => $item->po_number,
            'attachment_type' => $item->attachment_type,
            'is_addcost' => $item->is_addcost ?? 0,
            'remarks' => $item->remarks ?? '',
            'accountability' => $item->accountability,
            'accountable' => $item->accountable ?? '-',
            'additional_info' => $item->additional_info ?? '-',
            'acquisition_details' => $item->acquisition_details ?? '-',
            'asset_description' => $item->asset_description,
            'asset_specification' => $item->asset_specification ?? '-',
            'cellphone_number' => $item->cellphone_number ?? '-',
            'brand' => $item->brand ?? '-',
            'date_needed' => $item->date_needed ?? '-',
            'quantity' => $item->quantity ?? '-',
            'ordered' => $item->quantity + $deletedQuantity ?? '-',
            'delivered' => $item->quantity_delivered ?? '-',
            'remaining' => $item->quantity - $item->quantity_delivered ?? '-',
            'cancelled' => AssetRequest::onlyTrashed()->where('transaction_number', $item->transaction_number)->where('reference_number', $item->reference_number)->sum('quantity') ?? '-',
            'is_equal' => $isEqual,
//            'fixed_asset' => [
//                'id' => $item->fixedAsset->id ?? '-',
//                'vladimir_tag_number' => $item->fixedAsset->vladimir_tag_number ?? '-',
//            ],
            'requestor' => [
                'id' => $item->requestor->id,
                'username' => $item->requestor->username,
                'employee_id' => $item->requestor->employee_id,
                'firstname' => $item->requestor->firstname,
                'lastname' => $item->requestor->lastname,
            ],
            'type_of_request' => [
                'id' => $item->typeOfRequest->id,
                'type_of_request_name' => $item->typeOfRequest->type_of_request_name ?? '-',
            ],
            'company' => [
                'id' => $item->company->id,
                'company_code' => $item->company->company_code,
                'company_name' => $item->company->company_name,
            ],
            'business_unit' => [
                'id' => $item->businessUnit->id ?? '-',
                'business_unit_code' => $item->businessUnit->company_code ?? '-',
                'business_unit_name' => $item->businessUnit->company_name ?? '-',
            ],
            'department' => [
                'id' => $item->department->id,
                'department_code' => $item->department->department_code,
                'department_name' => $item->department->department_name,
                'sync_id' => $item->department->sync_id,
            ],
            'subunit' => [
                'id' => $item->subunit->id,
                'subunit_code' => $item->subunit->sub_unit_code,
                'subunit_name' => $item->subunit->sub_unit_name,
            ],
            'location' => [
                'id' => $item->location->id,
                'location_code' => $item->location->location_code,
                'location_name' => $item->location->location_name,
            ],
            'account_title' => [
                'id' => $item->accountTitle->id,
                'account_title_code' => $item->accountTitle->account_title_code,
                'account_title_name' => $item->accountTitle->account_title_name,
            ],
            'supplier' => [
                'id' => $item->supplier->id ?? '-',
                'supplier_code' => $item->supplier->supplier_code ?? '-',
                'supplier_name' => $item->supplier->supplier_name ?? '-',
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

    public function responseForFixedAsset($data)
    {
        if ($data instanceof LengthAwarePaginator) {
            $data->getCollection()->transform(function ($item) {
                return $this->transformFixedAsset($item);
            });
            return $data;
        } else if ($data instanceof Collection) {
            $data->transform(function ($item) {
                return $this->transformFixedAsset($item);
            });
            return $data;
        } else {
            return null;
        }
    }

    private function transformFixedAsset($item)
    {
        $item->additional_cost_count = $item->additionalCost ? $item->additionalCost->count() : 0;
        return [
            'additional_cost_count' => $item->additional_cost_count,
            'id' => $item->id,
            'requestor' => [
                'id' => $item->requestor->id ?? '-',
                'username' => $item->requestor->username ?? '-',
                'first_name' => $item->requestor->first_name ?? '-',
                'last_name' => $item->requestor->last_name ?? '-',
                'employee_id' => $item->requestor->employee_id ?? '-',
            ],
            'pr_number' => $item->pr_number ?? '-',
            'po_number' => $item->po_number ?? '-',
            'rr_number' => $item->rr_number ?? '-',
            'warehouse_number' => [
                'id' => $item->warehouseNumber->id ?? '-',
                'warehouse_number' => $item->warehouseNumber->warehouse_number ?? '-',
            ],
            'from_request' => $item->from_request ?? '-',
            'can_release' => $item->can_release ?? '-',
            'capex' => [
                'id' => $item->capex->id ?? '-',
                'capex' => $item->capex->capex ?? '-',
                'project_name' => $item->capex->project_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $item->subCapex->id ?? '-',
                'sub_capex' => $item->subCapex->sub_capex ?? '-',
                'sub_project' => $item->subCapex->sub_project ?? '-',
            ],
            'vladimir_tag_number' => $item->vladimir_tag_number,
            'tag_number' => $item->tag_number ?? '-',
            'tag_number_old' => $item->tag_number_old ?? '-',
            'asset_description' => $item->asset_description ?? '-',
            'type_of_request' => [
                'id' => $item->typeOfRequest->id ?? '-',
                'type_of_request_name' => $item->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $item->asset_specification ?? '-',
            'accountability' => $item->accountability ?? '-',
            'accountable' => $item->accountable ?? '-',
            'cellphone_number' => $item->cellphone_number ?? '-',
            'brand' => $item->brand ?? '-',
            'supplier' => [
                'id' => $item->supplier->id ?? '-',
                'supplier_code' => $item->supplier->supplier_code ?? '-',
                'supplier_name' => $item->supplier->supplier_name ?? '-',
            ],
            'division' => [
                'id' => $item->department->division->id ?? '-',
                'division_name' => $item->department->division->division_name ?? '-',
            ],
            'major_category' => [
                'id' => $item->majorCategory->id ?? '-',
                'major_category_name' => $item->majorCategory->major_category_name ?? '-',
            ],
            'minor_category' => [
                'id' => $item->minorCategory->id ?? '-',
                'minor_category_name' => $item->minorCategory->minor_category_name ?? '-',
            ],
            'est_useful_life' => $item->majorCategory->est_useful_life ?? '-',
            'voucher' => $item->voucher ?? '-',
            'voucher_date' => $item->voucher_date ?? '-',
            'receipt' => $item->receipt ?? '-',
            'quantity' => $item->quantity ?? '-',
            'depreciation_method' => $item->depreciation_method ?? '-',
            //                    'salvage_value' => $item->salvage_value,
            'acquisition_date' => $item->acquisition_date ?? '-',
            'acquisition_cost' => $item->acquisition_cost ?? '-',
            'scrap_value' => $item->formula->scrap_value ?? '-',
            'depreciable_basis' => $item->formula->depreciable_basis ?? '-',
            'accumulated_cost' => $item->formula->accumulated_cost ?? '-',
            'asset_status' => [
                'id' => $item->assetStatus->id ?? '-',
                'asset_status_name' => $item->assetStatus->asset_status_name ?? '-',
            ],
            'cycle_count_status' => [
                'id' => $item->cycleCountStatus->id ?? '-',
                'cycle_count_status_name' => $item->cycleCountStatus->cycle_count_status_name ?? '-',
            ],
            'depreciation_status' => [
                'id' => $item->depreciationStatus->id ?? '-',
                'depreciation_status_name' => $item->depreciationStatus->depreciation_status_name ?? '-',
            ],
            'movement_status' => [
                'id' => $item->movementStatus->id ?? '-',
                'movement_status_name' => $item->movementStatus->movement_status_name ?? '-',
            ],
            'is_additional_cost' => $item->is_additional_cost ?? '-',
            'is_old_asset' => $item->is_old_asset ?? '-',
            'status' => $item->is_active ?? '-',
            'care_of' => $item->care_of ?? '-',
            'months_depreciated' => $item->formula->months_depreciated ?? '-',
            'end_depreciation' => $item->formula->end_depreciation ?? '-',
            'depreciation_per_year' => $item->formula->depreciation_per_year ?? '-',
            'depreciation_per_month' => $item->formula->depreciation_per_month ?? '-',
            'remaining_book_value' => $item->formula->remaining_book_value ?? '-',
            'release_date' => $item->formula->release_date ?? '-',
            'start_depreciation' => $item->formula->start_depreciation ?? '-',
            'company' => [
                'id' => $item->department->company->id ?? '-',
                'company_code' => $item->department->company->company_code ?? '-',
                'company_name' => $item->department->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $item->department->businessUnit->id ?? '-',
                'business_unit_code' => $item->department->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $item->department->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $item->department->id ?? '-',
                'department_code' => $item->department->department_code ?? '-',
                'department_name' => $item->department->department_name ?? '-',
            ],
            'charged_department' => [
                'id' => $additional_cost->department->id ?? '-',
                'charged_department_code' => $additional_cost->department->department_code ?? '-',
                'charged_department_name' => $additional_cost->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $item->location->id ?? '-',
                'location_code' => $item->location->location_code ?? '-',
                'location_name' => $item->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $item->accountTitle->id ?? '-',
                'account_title_code' => $item->accountTitle->account_title_code ?? '-',
                'account_title_name' => $item->accountTitle->account_title_name ?? '-',
            ],
            'remarks' => $item->remarks,
            'print_count' => $item->print_count,
            'print' => $item->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $item->last_printed,
            'tagging' => $item->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'additional_cost' => isset($item->additionalCost) ? $item->additionalCost->map(function ($additional_cost) {
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
                        'id' => $additional_cost->supplier->id ?? '-',
                        'supplier_code' => $additional_cost->supplier->supplier_code ?? '-',
                        'supplier_name' => $additional_cost->supplier->supplier_name ?? '-',
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
                        'id' => $additional_cost->department->businessUnit->id ?? '-',
                        'business_unit_code' => $additional_cost->department->businessUnit->business_unit_code ?? '-',
                        'business_unit_name' => $additional_cost->department->businessUnit->business_unit_name ?? '-',
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

    public function responseForAdditionalCost($data)
    {
        if ($data instanceof LengthAwarePaginator) {
            $data->getCollection()->transform(function ($item) {
                return $this->transformAdditionalCost($item);
            });
            return $data;
        } else if ($data instanceof Collection) {
            $data->transform(function ($item) {
                return $this->transformAdditionalCost($item);
            });
            return $data;
        } else {
            return null;
        }
    }

    private function transformAdditionalCost($item)
    {
        $signature = $item->getMedia(Str::slug($item->received_by) . '-signature')->first();
        return [
            //            'total_adcost' => $this->calculationRepository->getTotalCost($item->fixedAsset->additionalCosts),
            'id' => $item->id,
            'add_cost_sequence' => $item->add_cost_sequence,
            'fixed_asset' => [
                'id' => $item->fixedAsset->id,
                'vladimir_tag_number' => $item->fixedAsset->vladimir_tag_number,
                'asset_description' => $item->fixedAsset->asset_description,
            ],
            'requestor_id' => [
                'id' => $item->requestor->id ?? '-',
                'username' => $item->requestor->username ?? '-',
                'firstname' => $item->requestor->firstname ?? '-',
                'lastname' => $item->requestor->lastname ?? '-',
                'employee_id' => $item->requestor->employee_id ?? '-',
            ],
            'pr_number' => $item->pr_number ?? '-',
            'po_number' => $item->po_number ?? '-',
            'rr_number' => $item->rr_number ?? '-',
            'is_released' => $item->is_released ?? '-',
            'warehouse_number' => [
                'id' => $item->warehouseNumber->id ?? '-',
                'warehouse_number' => $item->warehouseNumber->warehouse_number ?? '-',
            ],
            'from_request' => $item->from_request ?? '-',
            'can_release' => $item->can_release ?? '-',
            'capex' => [
                'id' => $item->fixedAsset->capex->id ?? '-',
                'capex' => $item->fixedAsset->capex->capex ?? '-',
                'project_name' => $item->fixedAsset->capex->project_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $item->fixedAsset->subCapex->id ?? '-',
                'sub_capex' => $item->fixedAsset->subCapex->sub_capex ?? '-',
                'sub_project' => $item->fixedAsset->subCapex->sub_project ?? '-',
            ],
            'vladimir_tag_number' => $item->fixedAsset->vladimir_tag_number,
            'tag_number' => $item->fixedAsset->tag_number,
            'tag_number_old' => $item->fixedAsset->tag_number_old,
            'asset_description' => $item->asset_description,
            'type_of_request' => [
                'id' => $item->typeOfRequest->id ?? '-',
                'type_of_request_name' => $item->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $item->asset_specification,
            'accountability' => $item->accountability,
            'accountable' => $item->accountable,
            'received_by' => $item->received_by ?? '-',
            'cellphone_number' => $item->cellphone_number,
            'brand' => $item->brand ?? '-',
            'supplier' => [
                'id' => $item->supplier->id ?? '-',
                'supplier_code' => $item->supplier->supplier_code ?? '-',
                'supplier_name' => $item->supplier->supplier_name ?? '-',
            ],
            'division' => [
                'id' => $item->department->division->id ?? '-',
                'division_name' => $item->department->division->division_name ?? '-',
            ],
            'major_category' => [
                'id' => $item->majorCategory->id ?? '-',
                'major_category_name' => $item->majorCategory->major_category_name ?? '-',
            ],
            'minor_category' => [
                'id' => $item->minorCategory->id ?? '-',
                'minor_category_name' => $item->minorCategory->minor_category_name ?? '-',
            ],
            'est_useful_life' => $item->majorCategory->est_useful_life ?? '-',
            'voucher' => $item->voucher ?? '-',
            'voucher_date' => $item->voucher_date ?? '-',
            'receipt' => $item->receipt ?? '-',
            'quantity' => $item->quantity ?? '-',
            'depreciation_method' => $item->depreciation_method ?? '-',
            //                    'salvage_value' => $item->salvage_value,
            'acquisition_date' => $item->acquisition_date ?? '-',
            'acquisition_cost' => $item->acquisition_cost ?? 0,
            'scrap_value' => $item->formula->scrap_value ?? 0,
            'depreciable_basis' => $item->formula->depreciable_basis ?? 0,
            'accumulated_cost' => $item->formula->accumulated_cost ?? 0,
            'asset_status' => [
                'id' => $item->assetStatus->id ?? '-',
                'asset_status_name' => $item->assetStatus->asset_status_name ?? '-',
            ],
            'cycle_count_status' => [
                'id' => $item->cycleCountStatus->id ?? '-',
                'cycle_count_status_name' => $item->cycleCountStatus->cycle_count_status_name ?? '-',
            ],
            'depreciation_status' => [
                'id' => $item->depreciationStatus->id ?? '-',
                'depreciation_status_name' => $item->depreciationStatus->depreciation_status_name ?? '-',
            ],
            'movement_status' => [
                'id' => $item->movementStatus->id ?? '-',
                'movement_status_name' => $item->movementStatus->movement_status_name ?? '-',
            ],
            'is_additional_cost' => $item->is_additional_cost ?? '-',
            'care_of' => $item->care_of ?? '-',
            'months_depreciated' => $item->formula->months_depreciated ?? '-',
            'end_depreciation' => $item->formula->end_depreciation ?? '-',
            'depreciation_per_year' => $item->formula->depreciation_per_year ?? '-',
            'depreciation_per_month' => $item->formula->depreciation_per_month ?? '-',
            'remaining_book_value' => $item->formula->remaining_book_value ?? '-',
            'release_date' => $item->formula->release_date ?? '-',
            'start_depreciation' => $item->formula->start_depreciation ?? '-',
            'company' => [
                'id' => $item->department->company->id ?? '-',
                'company_code' => $item->department->company->company_code ?? '-',
                'company_name' => $item->department->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $item->department->businessUnit->id ?? '-',
                'business_unit_code' => $item->department->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $item->department->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $item->department->id ?? '-',
                'department_code' => $item->department->department_code ?? '-',
                'department_name' => $item->department->department_name ?? '-',
            ],
            'charged_department' => [
                'id' => $item->department->id ?? '-',
                'charged_department_code' => $item->department->department_code ?? '-',
                'charged_department_name' => $item->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $item->location->id ?? '-',
                'location_code' => $item->location->location_code ?? '-',
                'location_name' => $item->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $item->accountTitle->id ?? '-',
                'account_title_code' => $item->accountTitle->account_title_code ?? '-',
                'account_title_name' => $item->accountTitle->account_title_name ?? '-',
            ],
            'signature' => $signature ? [
                'id' => $signature->id,
                'file_name' => $signature->file_name,
                'file_path' => $signature->getPath(),
                'file_url' => $signature->getUrl(),
                'collection_name' => $signature->collection_name,
                'viewing' => $this->convertImageToBase64($signature->getPath()),
            ] : null,
            'main' => [
                'id' => $item->fixedAsset->id,
                'capex' => [
                    'id' => $item->fixedAsset->capex->id ?? '-',
                    'capex' => $item->fixedAsset->capex->capex ?? '-',
                    'project_name' => $item->fixedAsset->capex->project_name ?? '-',
                ],
                'sub_capex' => [
                    'id' => $item->fixedAsset->subCapex->id ?? '-',
                    'sub_capex' => $item->fixedAsset->subCapex->sub_capex ?? '-',
                    'sub_project' => $item->fixedAsset->subCapex->sub_project ?? '-',
                ],
                'vladimir_tag_number' => $item->fixedAsset->vladimir_tag_number,
                'tag_number' => $item->fixedAsset->tag_number,
                'tag_number_old' => $item->fixedAsset->tag_number_old,
                'asset_description' => $item->fixedAsset->asset_description,
                'type_of_request' => [
                    'id' => $item->fixedAsset->typeOfRequest->id ?? '-',
                    'type_of_request_name' => $item->fixedAsset->typeOfRequest->type_of_request_name ?? '-',
                ],
                'asset_specification' => $item->fixedAsset->asset_specification,
                'accountability' => $item->fixedAsset->accountability,
                'accountable' => $item->fixedAsset->accountable,
                'cellphone_number' => $item->fixedAsset->cellphone_number,
                'brand' => $item->fixedAsset->brand ?? '-',
                'division' => [
                    'id' => $item->fixedAsset->department->division->id ?? '-',
                    'division_name' => $item->fixedAsset->department->division->division_name ?? '-',
                ],
                'major_category' => [
                    'id' => $item->fixedAsset->majorCategory->id ?? '-',
                    'major_category_name' => $item->fixedAsset->majorCategory->major_category_name ?? '-',
                ],
                'minor_category' => [
                    'id' => $item->fixedAsset->minorCategory->id ?? '-',
                    'minor_category_name' => $item->fixedAsset->minorCategory->minor_category_name ?? '-',
                ],
                'est_useful_life' => $item->fixedAsset->majorCategory->est_useful_life ?? '-',
                'voucher' => $item->fixedAsset->voucher,
                'voucher_date' => $item->fixedAsset->voucher_date ?? '-',
                'receipt' => $item->fixedAsset->receipt,
                'quantity' => $item->fixedAsset->quantity,
                'depreciation_method' => $item->fixedAsset->depreciation_method,
                //                    'salvage_value' => $item->fixedAsset->salvage_value,
                'acquisition_date' => $item->fixedAsset->acquisition_date,
                'acquisition_cost' => $item->fixedAsset->acquisition_cost,
                'scrap_value' => $item->fixedAsset->formula->scrap_value,
                'depreciable_basis' => $item->fixedAsset->formula->depreciable_basis,
                'accumulated_cost' => $item->fixedAsset->formula->accumulated_cost,
                'asset_status' => [
                    'id' => $item->fixedAsset->assetStatus->id ?? '-',
                    'asset_status_name' => $item->fixedAsset->assetStatus->asset_status_name ?? '-',
                ],
                'cycle_count_status' => [
                    'id' => $item->fixedAsset->cycleCountStatus->id ?? '-',
                    'cycle_count_status_name' => $item->fixedAsset->cycleCountStatus->cycle_count_status_name ?? '-',
                ],
                'depreciation_status' => [
                    'id' => $item->fixedAsset->depreciationStatus->id ?? '-',
                    'depreciation_status_name' => $item->fixedAsset->depreciationStatus->depreciation_status_name ?? '-',
                ],
                'movement_status' => [
                    'id' => $item->fixedAsset->movementStatus->id ?? '-',
                    'movement_status_name' => $item->fixedAsset->movementStatus->movement_status_name ?? '-',
                ],
                'is_additional_cost' => $item->fixedAsset->is_additional_cost,
                'is_old_asset' => $item->fixedAsset->is_old_asset,
                'status' => $item->fixedAsset->is_active,
                'care_of' => $item->fixedAsset->care_of,
                'months_depreciated' => $item->fixedAsset->formula->months_depreciated,
                'end_depreciation' => $item->fixedAsset->formula->end_depreciation,
                'depreciation_per_year' => $item->fixedAsset->formula->depreciation_per_year,
                'depreciation_per_month' => $item->fixedAsset->formula->depreciation_per_month,
                'remaining_book_value' => $item->fixedAsset->formula->remaining_book_value,
                'release_date' => $item->fixedAsset->formula->release_date ?? '-',
                'start_depreciation' => $item->fixedAsset->formula->start_depreciation,
                'company' => [
                    'id' => $item->fixedAsset->department->company->id ?? '-',
                    'company_code' => $item->fixedAsset->department->company->company_code ?? '-',
                    'company_name' => $item->fixedAsset->department->company->company_name ?? '-',
                ],
                'department' => [
                    'id' => $item->fixedAsset->department->id ?? '-',
                    'department_code' => $item->fixedAsset->department->department_code ?? '-',
                    'department_name' => $item->fixedAsset->department->department_name ?? '-',
                ],
                'charged_department' => [
                    'id' => $item->department->id ?? '-',
                    'charged_department_code' => $item->department->department_code ?? '-',
                    'charged_department_name' => $item->department->department_name ?? '-',
                ],
                'location' => [
                    'id' => $item->fixedAsset->location->id ?? '-',
                    'location_code' => $item->fixedAsset->location->location_code ?? '-',
                    'location_name' => $item->fixedAsset->location->location_name ?? '-',
                ],
                'account_title' => [
                    'id' => $item->fixedAsset->accountTitle->id ?? '-',
                    'account_title_code' => $item->fixedAsset->accountTitle->account_title_code ?? '-',
                    'account_title_name' => $item->fixedAsset->accountTitle->account_title_name ?? '-',
                ],
                'remarks' => $item->fixedAsset->remarks,
                'print_count' => $item->fixedAsset->print_count,
                'last_printed' => $item->fixedAsset->last_printed,
            ],
        ];
    }


}
