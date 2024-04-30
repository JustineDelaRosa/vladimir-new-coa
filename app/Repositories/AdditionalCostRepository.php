<?php

namespace App\Repositories;

use App\Models\AdditionalCost;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\Status\DepreciationStatus;
use App\Models\SubCapex;
use Carbon\Carbon;

class AdditionalCostRepository
{

    protected $calculationRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
    }

    public function storeAdditionalCost($request, $businessUnitQuery)
    {
        $majorCategory = $this->getMajorCategory($request['major_category_id']);
        $this->checkDepreciationStatus($request, $majorCategory);

        $formulaData = $this->prepareFormulaDataForStore($request, $majorCategory);
        $formula = Formula::create($formulaData);

        $additionalCostData = $this->prepareAdditionalCostDataForStore($request, $businessUnitQuery);
        $formula->additionalCost()->create($additionalCostData);

        return $formula->additionalCost()->with('formula')->first();
    }

    private function prepareFormulaDataForStore($request, $majorCategory): array
    {
        $depreciationMethod = strtoupper($request['depreciation_method']);
        return [
            'depreciation_method' => $depreciationMethod == 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod)),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'scrap_value' => $request['scrap_value'] ?? 0,
            'depreciable_basis' => $request['depreciable_basis'] ?? 0,
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'] ?? 0,
            'release_date' => $request['release_date'] ?? Null,
            'end_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getEndDepreciation(
                    $this->calculationRepository->getStartDepreciation($request['release_date']),
                    $majorCategory->est_useful_life,
                    $depreciationMethod == 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod))
                )
                : null,
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'start_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getStartDepreciation($request['release_date'])
                : null
        ];
    }

    private function prepareAdditionalCostDataForStore($request, $businessUnitQuery): array
    {
        return [
            'fixed_asset_id' => $request['fixed_asset_id'],
            'add_cost_sequence' => $this->getAddCostSequence($request['fixed_asset_id']) ?? '-',
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'] ?? '-')),
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'voucher_date' => $request['voucher_date'] ?? null,
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $businessUnitQuery->company_sync_id)->first()->id ?? null,
            'business_unit_id' => $request['business_unit_id'],
            'department_id' => $request['department_id'],
            'unit_id' => $request['unit_id'],
            'subunit_id' => $request['subunit_id'] ?? '-',
            'location_id' => $request['location_id'] ?? '-',
            'account_id' => $request['account_title_id'],
            'uom_id' => $request['uom_id'],
        ];
    }

    public function updateAdditionalCost($request, $businessUnitQuery, $id)
    {
        $majorCategory = $this->getMajorCategory($request['major_category_id']);
        $this->checkDepreciationStatus($request, $majorCategory);

        $additionalCost = AdditionalCost::find($id);
        $additionalCostData = $this->prepareAdditionalCostDataForUpdate($request, $businessUnitQuery);
        $additionalCost->update($additionalCostData);

        $formulaData = $this->prepareFormulaDataForUpdate($request, $majorCategory);
        $additionalCost->formula()->update($formulaData);

        return $additionalCost;
    }

    private function prepareAdditionalCostDataForUpdate($request, $businessUnitQuery): array
    {
        return [
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'po_number' => $request['po_number'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'] ?? '-')),
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'voucher_date' => $request['voucher_date'] ?? null,
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $businessUnitQuery->company_sync_id)->first()->id ?? null,
            'business_unit_id' => $request['business_unit_id'],
            'department_id' => $request['department_id'],
            'unit_id' => $request['unit_id'],
            'subunit_id' => $request['subunit_id'] ?? '-',
            'location_id' => $request['location_id'] ?? '-',
            'account_id' => $request['account_title_id'],
            'uom_id' => $request['uom_id'],
        ];
    }

    private function prepareFormulaDataForUpdate($request, $majorCategory): array
    {
        $depreciationMethod = strtoupper($request['depreciation_method']);
        return [
            'depreciation_method' => $depreciationMethod == 'STL'
                ? $depreciationMethod
                : ucwords(strtolower($depreciationMethod)),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'scrap_value' => $request['scrap_value'] ?? 0,
            'depreciable_basis' => $request['depreciable_basis'] ?? 0,
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'] ?? 0,
            'release_date' => $request['release_date'] ?? Null,
            'end_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getEndDepreciation(
                    $this->calculationRepository->getStartDepreciation($request['release_date']),
                    $majorCategory->est_useful_life,
                    $depreciationMethod == 'STL'
                        ? $depreciationMethod
                        : ucwords(strtolower($depreciationMethod))
                )
                : null,
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'start_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getStartDepreciation($request['release_date'])
                : null
        ];
    }

    private function getMajorCategory($id)
    {
        return MajorCategory::withTrashed()->where('id', $id)->first();
    }

    private function checkDepreciationStatus($request, $majorCategory)
    {
        $depreciationMethod = strtoupper($request['depreciation_method']);
        if ($depreciationMethod !== 'DONATION') {
            $depstatus = DepreciationStatus::where('id', $request['depreciation_status_id'])->first();
            if ($depstatus->depreciation_status_name == 'Fully Depreciated' && isset($request['release_date'])) {
                $end_depreciation = $this->calculationRepository->getEndDepreciation(
                    $this->calculationRepository->getStartDepreciation($request['release_date']),
                    $majorCategory->est_useful_life,
                    $depreciationMethod == 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod))
                );
                if ($end_depreciation >= Carbon::now()) {
                    return 'Not yet fully depreciated';
                }
            }
        }
    }

    public function transformAdditionalCost($additionalCost): array
    {
        $additionalCostArr = [];

        foreach ($additionalCost as $additionalCosts) {
            $additionalCostArr[] = $this->transformSingleAdditionalCost($additionalCosts);
        }
        return $additionalCostArr;
    }

    public function transformSingleAdditionalCost($additional_cost): array
    {
        return [
            //            'total_adcost' => $this->calculationRepository->getTotalCost($additional_cost->fixedAsset->additionalCosts),
            'id' => $additional_cost->id,
            'add_cost_sequence' => $additional_cost->add_cost_sequence,
            'fixed_asset' => [
                'id' => $additional_cost->fixedAsset->id,
                'vladimir_tag_number' => $additional_cost->fixedAsset->vladimir_tag_number,
                'asset_description' => $additional_cost->fixedAsset->asset_description,
            ],
            'requestor_id' => [
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
            'capex' => [
                'id' => $additional_cost->fixedAsset->capex->id ?? '-',
                'capex' => $additional_cost->fixedAsset->capex->capex ?? '-',
                'project_name' => $additional_cost->fixedAsset->capex->project_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $additional_cost->fixedAsset->subCapex->id ?? '-',
                'sub_capex' => $additional_cost->fixedAsset->subCapex->sub_capex ?? '-',
                'sub_project' => $additional_cost->fixedAsset->subCapex->sub_project ?? '-',
            ],
            'vladimir_tag_number' => $additional_cost->fixedAsset->vladimir_tag_number,
            'tag_number' => $additional_cost->fixedAsset->tag_number,
            'tag_number_old' => $additional_cost->fixedAsset->tag_number_old,
            'asset_description' => $additional_cost->asset_description,
            'type_of_request' => [
                'id' => $additional_cost->typeOfRequest->id ?? '-',
                'type_of_request_name' => $additional_cost->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $additional_cost->asset_specification,
            'accountability' => $additional_cost->accountability,
            'accountable' => $additional_cost->accountable,
            'cellphone_number' => $additional_cost->cellphone_number,
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
            'unit_of_measure' => [
                'id' => $additional_cost->uom->id ?? '-',
                'uom_code' => $additional_cost->uom->uom_code ?? '-',
                'uom_name' => $additional_cost->uom->uom_name ?? '-',
            ],
            'est_useful_life' => $additional_cost->majorCategory->est_useful_life ?? '-',
            'voucher' => $additional_cost->voucher ?? '-',
            'voucher_date' => $additional_cost->voucher_date ?? '-',
            'receipt' => $additional_cost->receipt,
            'quantity' => $additional_cost->quantity,
            'depreciation_method' => $additional_cost->depreciation_method ?? '-',
            //                    'salvage_value' => $additional_cost->salvage_value,
            'acquisition_date' => $additional_cost->acquisition_date,
            'acquisition_cost' => $additional_cost->acquisition_cost,
            'scrap_value' => $additional_cost->formula->scrap_value,
            'depreciable_basis' => $additional_cost->formula->depreciable_basis,
            'accumulated_cost' => $additional_cost->formula->accumulated_cost,
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
            'is_additional_cost' => $additional_cost->is_additional_cost,
            'care_of' => $additional_cost->care_of ?? '-',
            'months_depreciated' => $additional_cost->formula->months_depreciated,
            'end_depreciation' => $additional_cost->formula->end_depreciation,
            'depreciation_per_year' => $additional_cost->formula->depreciation_per_year,
            'depreciation_per_month' => $additional_cost->formula->depreciation_per_month,
            'remaining_book_value' => $additional_cost->formula->remaining_book_value,
            'release_date' => $additional_cost->formula->release_date ?? '-',
            'start_depreciation' => $additional_cost->formula->start_depreciation,
            'company' => [
                'id' => $additional_cost->company->id ?? '-',
                'company_code' => $additional_cost->company->company_code ?? '-',
                'company_name' => $additional_cost->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $additional_cost->businessUnit->id ?? '-',
                'business_unit_code' => $additional_cost->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $additional_cost->businessUnit->business_unit_name ?? '-',
            ],
            'unit' => [
                'id' => $fixed_asset->unit->id ?? '-',
                'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                'unit_name' => $fixed_asset->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $additional_cost->subunit->id ?? '-',
                'subunit_code' => $additional_cost->subunit->sub_unit_code ?? '-',
                'subunit_name' => $additional_cost->subunit->sub_unit_name ?? '-',
            ],
            'department' => [
                'id' => $additional_cost->department->id ?? '-',
                'department_code' => $additional_cost->department->department_code ?? '-',
                'department_name' => $additional_cost->department->department_name ?? '-',
            ],
            'charged_department' => [
                'id' => $additional_cost->department->id ?? '-',
                'department_code' => $additional_cost->department->department_code ?? '-',
                'department_name' => $additional_cost->department->department_name ?? '-',
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
            'main' => [
                'id' => $additional_cost->fixedAsset->id,
                'capex' => [
                    'id' => $additional_cost->fixedAsset->capex->id ?? '-',
                    'capex' => $additional_cost->fixedAsset->capex->capex ?? '-',
                    'project_name' => $additional_cost->fixedAsset->capex->project_name ?? '-',
                ],
                'sub_capex' => [
                    'id' => $additional_cost->fixedAsset->subCapex->id ?? '-',
                    'sub_capex' => $additional_cost->fixedAsset->subCapex->sub_capex ?? '-',
                    'sub_project' => $additional_cost->fixedAsset->subCapex->sub_project ?? '-',
                ],
                'capex_number' => $additional_cost->fixedAsset->capex_number ?? '-',
                'vladimir_tag_number' => $additional_cost->fixedAsset->vladimir_tag_number,
                'tag_number' => $additional_cost->fixedAsset->tag_number,
                'tag_number_old' => $additional_cost->fixedAsset->tag_number_old,
                'asset_description' => $additional_cost->fixedAsset->asset_description,
                'type_of_request' => [
                    'id' => $additional_cost->fixedAsset->typeOfRequest->id ?? '-',
                    'type_of_request_name' => $additional_cost->fixedAsset->typeOfRequest->type_of_request_name ?? '-',
                ],
                'asset_specification' => $additional_cost->fixedAsset->asset_specification,
                'accountability' => $additional_cost->fixedAsset->accountability,
                'accountable' => $additional_cost->fixedAsset->accountable,
                'cellphone_number' => $additional_cost->fixedAsset->cellphone_number,
                'brand' => $additional_cost->fixedAsset->brand ?? '-',
                'division' => [
                    'id' => $additional_cost->fixedAsset->department->division->id ?? '-',
                    'division_name' => $additional_cost->fixedAsset->department->division->division_name ?? '-',
                ],
                'major_category' => [
                    'id' => $additional_cost->fixedAsset->majorCategory->id ?? '-',
                    'major_category_name' => $additional_cost->fixedAsset->majorCategory->major_category_name ?? '-',
                ],
                'minor_category' => [
                    'id' => $additional_cost->fixedAsset->minorCategory->id ?? '-',
                    'minor_category_name' => $additional_cost->fixedAsset->minorCategory->minor_category_name ?? '-',
                ],
                'unit_of_measure' => [
                    'id' => $additional_cost->fixedAsset->uom->id ?? '-',
                    'uom_code' => $additional_cost->fixedAsset->uom->uom_code ?? '-',
                    'uom_name' => $additional_cost->fixedAsset->uom->uom_name ?? '-',
                ],
                'est_useful_life' => $additional_cost->fixedAsset->majorCategory->est_useful_life ?? '-',
                'voucher' => $additional_cost->fixedAsset->voucher ?? '-',
                'voucher_date' => $additional_cost->fixedAsset->voucher_date ?? '-',
                'receipt' => $additional_cost->fixedAsset->receipt,
                'quantity' => $additional_cost->fixedAsset->quantity,
                'depreciation_method' => $additional_cost->fixedAsset->depreciation_method?? '-',
                //                    'salvage_value' => $additional_cost->fixedAsset->salvage_value,
                'acquisition_date' => $additional_cost->fixedAsset->acquisition_date,
                'acquisition_cost' => $additional_cost->fixedAsset->acquisition_cost,
                'scrap_value' => $additional_cost->fixedAsset->formula->scrap_value,
                'depreciable_basis' => $additional_cost->fixedAsset->formula->depreciable_basis,
                'accumulated_cost' => $additional_cost->fixedAsset->formula->accumulated_cost,
                'asset_status' => [
                    'id' => $additional_cost->fixedAsset->assetStatus->id ?? '-',
                    'asset_status_name' => $additional_cost->fixedAsset->assetStatus->asset_status_name ?? '-',
                ],
                'cycle_count_status' => [
                    'id' => $additional_cost->fixedAsset->cycleCountStatus->id ?? '-',
                    'cycle_count_status_name' => $additional_cost->fixedAsset->cycleCountStatus->cycle_count_status_name ?? '-',
                ],
                'depreciation_status' => [
                    'id' => $additional_cost->fixedAsset->depreciationStatus->id ?? '-',
                    'depreciation_status_name' => $additional_cost->fixedAsset->depreciationStatus->depreciation_status_name ?? '-',
                ],
                'movement_status' => [
                    'id' => $additional_cost->fixedAsset->movementStatus->id ?? '-',
                    'movement_status_name' => $additional_cost->fixedAsset->movementStatus->movement_status_name ?? '-',
                ],
                'is_additional_cost' => $additional_cost->fixedAsset->is_additional_cost,
                'is_old_asset' => $additional_cost->fixedAsset->is_old_asset,
                'status' => $additional_cost->fixedAsset->is_active,
                'care_of' => $additional_cost->fixedAsset->care_of ?? '-',
                'months_depreciated' => $additional_cost->fixedAsset->formula->months_depreciated,
                'end_depreciation' => $additional_cost->fixedAsset->formula->end_depreciation,
                'depreciation_per_year' => $additional_cost->fixedAsset->formula->depreciation_per_year,
                'depreciation_per_month' => $additional_cost->fixedAsset->formula->depreciation_per_month,
                'remaining_book_value' => $additional_cost->fixedAsset->formula->remaining_book_value,
                'release_date' => $additional_cost->fixedAsset->formula->release_date ?? '-',
                'start_depreciation' => $additional_cost->fixedAsset->formula->start_depreciation,
                'company' => [
                    'id' => $additional_cost->company->id ?? '-',
                    'company_code' => $additional_cost->company->company_code ?? '-',
                    'company_name' => $additional_cost->company->company_name ?? '-',
                ],
                'business_unit' => [
                    'id' => $additional_cost->businessUnit->id ?? '-',
                    'business_unit_code' => $additional_cost->businessUnit->business_unit_code ?? '-',
                    'business_unit_name' => $additional_cost->businessUnit->business_unit_name ?? '-',
                ],
                'unit' => [
                    'id' => $fixed_asset->unit->id ?? '-',
                    'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                    'unit_name' => $fixed_asset->unit->unit_name ?? '-',
                ],
                'subunit' => [
                    'id' => $additional_cost->subunit->id ?? '-',
                    'subunit_code' => $additional_cost->subunit->sub_unit_code ?? '-',
                    'subunit_name' => $additional_cost->subunit->sub_unit_name ?? '-',
                ],
                'department' => [
                    'id' => $additional_cost->department->id ?? '-',
                    'department_code' => $additional_cost->department->department_code ?? '-',
                    'department_name' => $additional_cost->department->department_name ?? '-',
                ],
                'charged_department' => [
                    'id' => $additional_cost->department->id ?? '-',
                    'department_code' => $additional_cost->department->department_code ?? '-',
                    'department_name' => $additional_cost->department->department_name ?? '-',
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
                'remarks' => $additional_cost->fixedAsset->remarks,
                'print_count' => $additional_cost->fixedAsset->print_count,
                'last_printed' => $additional_cost->fixedAsset->last_printed,
            ],
        ];
    }

    public function getAddCostSequence($fixed_asset_id): string
    {
        // Get all the additional costs for the given fixed asset
        $additional_costs = AdditionalCost::where('fixed_asset_id', $fixed_asset_id)
            ->orderBy('id')->get();

        // Default next letter is 'A'
        $next_letter = 'A';

        if (!$additional_costs->isEmpty()) {
            // Get the sequence of the last additional cost
            $last_sequence = $additional_costs->last()->add_cost_sequence;

            // Get the last letter of the sequence
            $last_letter = strtoupper(substr($last_sequence, -1));

            // If the last letter isn't 'Z', get the next letter in the alphabet
            if ($last_letter !== 'Z') {
                $next_letter = chr(ord($last_letter) + 1);
            }
        }

        return $next_letter;
    }
}
