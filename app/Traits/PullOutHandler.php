<?php

namespace App\Traits;

use App\Models\PullOut;

trait PullOutHandler
{

    public function pullOutData($pullOut)
    {
        $pullOut = $pullOut->map(function ($item) {
            $pullOut = $item->pullout->first();


            $canEdit = 1;
            $canDelete = 0;
            if ($item->is_received || $item->is_fa_approved) {
                $canEdit = 0;
            }
            if ($item->statis == 'Returned' || $item->status == 'For Approval of Approver 1') {
                $canDelete = 1;
            }

            return [
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
                'id' => $item->id,
                'assets' => $item->pullout->map(function ($pulloutMovement) {
                    return $this->transformSingleFixedAssetShowData($pulloutMovement->fixedAsset, null);
                })->values(),
                'care_of' => $pullOut->care_of,
                'remarks' => $pullOut->remarks,
                'requester' => [
                    'id' => $item->requester->id,
                    'username' => $item->requester->username,
                    'first_name' => $item->requester->firstname,
                    'last_name' => $item->requester->lastname,
                    'employee_id' => $item->requester->employee_id,
                ],
            ];
        });

        return $pullOut;
    }

    public function nextPullOutData($pullOutMovement)
    {
        $pullout = $pullOutMovement->pullout->first();
        return [
            'pullout_number' => $pullOutMovement->id,
            'assets' => $pullOutMovement->pullout->map(function ($transferMovement) {
                return $this->transformSingleFixedAssetShowData($transferMovement->fixedAsset);
            })->values(),
            'care_of' => $pullout->care_of,
            'description' => $pullout->description,
            'remarks' => $pullout->remarks,
            'requester' => [
                'id' => $pullOutMovement->requester->id,
                'username' => $pullOutMovement->requester->username,
                'first_name' => $pullOutMovement->requester->firstname,
                'last_name' => $pullOutMovement->requester->lastname,
                'employee_id' => $pullOutMovement->requester->employee_id,
            ],
        ];
    }

    private function transformSingleFixedAssetShowData($fixed_asset, $movementNumber = null, $transfer = null): array
    {
//        $fixed_asset->additional_cost_count = $fixed_asset->additionalCost ? $fixed_asset->additionalCost->count() : 0;
        $attachment = $movementNumber ? $movementNumber->getMedia('attachments')->all() : null;
        $transfer = $transfer ?: null;
        return [
//            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id ?? '-',
//            'is_received' => $transfer ? ($transfer->whereNotNull('received_at') ? 1 : 0) : '-',
            'evaluation' => $fixed_asset->pullout->first()->evaluation ? $fixed_asset->pullout->first()->evaluation : 'Not yet evaluated',
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->firstname ?? '-',
                'last_name' => $fixed_asset->requestor->lastname ?? '-',
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
            'capex_number' => $fixed_asset->capex_number ?? '-',
            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number ?? '-',
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
            'unit_of_measure' => [
                'id' => $fixed_asset->uom->id ?? '-',
                'uom_code' => $fixed_asset->uom->uom_code ?? '-',
                'uom_name' => $fixed_asset->uom->uom_name ?? '-',
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
                'id' => $fixed_asset->company->id ?? '-',
                'company_code' => $fixed_asset->company->company_code ?? '-',
                'company_name' => $fixed_asset->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->businessUnit->id ?? '-',
                'business_unit_code' => $fixed_asset->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'unit' => [
                'id' => $fixed_asset->unit->id ?? '-',
                'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                'unit_name' => $fixed_asset->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $fixed_asset->subunit->id ?? '-',
                'subunit_code' => $fixed_asset->subunit->sub_unit_code ?? '-',
                'subunit_name' => $fixed_asset->subunit->sub_unit_name ?? '-',
            ],
            'charged_department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->location->id ?? '-',
                'location_code' => $fixed_asset->location->location_code ?? '-',
                'location_name' => $fixed_asset->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
            ],
            'remarks' => $fixed_asset->remarks ?? '-',
            'received_at' => $transfer->received_at ?? '-',
            'print_count' => $fixed_asset->print_count ?? '-',
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag' ?? '-',
            'last_printed' => $fixed_asset->last_printed,
            'tagging' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'attachments' => $attachment ? collect($attachment)->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'name' => $attachment->file_name,
                    'url' => $attachment->getUrl(),
                ];
            }) : collect([]),
            /*            'additional_cost' => isset($fixed_asset->additionalCost) ? $fixed_asset->additionalCost->map(function ($additional_cost) {
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
                                'unit_of_measure' => [
                                    'id' => $additional_cost->uom->id ?? '-',
                                    'uom_code' => $additional_cost->uom->uom_code ?? '-',
                                    'uom_name' => $additional_cost->uom->uom_name ?? '-',
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
                        }) : [],*/
        ];
    }


    public function itemsToBeFullOutView($userRoleName)
    {
         $pullOut = PullOut::where('care_of', $userRoleName)
            ->whereHas('movementNumber', function ($query) {
                $query->where('is_picked_up', false);
            })->get();
        return $pullOut;
    }
}
