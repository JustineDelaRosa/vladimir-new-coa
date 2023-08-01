<?php

namespace App\Repositories;

use App\Models\AdditionalCost;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\SubCapex;

class AdditionalCostRepository
{

    protected $calculationRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
    }

    public function storeAdditionalCost($request, $departmentQuery)
    {

        $majorCategory = MajorCategory::withTrashed()->where('id', $request['major_category_id'])->first();
        $additionalCost = AdditionalCost::create([
            'fixed_asset_id' => $request['fixed_asset_id'],
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'])) ?? '-',
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request['department_id'],
            'location_id' => Location::where('sync_id', $departmentQuery->location_sync_id)->first()->id ?? null,
            'account_id' => $request['account_title_id'],
        ]);

        $additionalCost->formula()->create([
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'scrap_value' => $request['scrap_value'],
            'depreciable_basis' => $request['depreciable_basis'],
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'],
            'end_depreciation' => $this->calculationRepository->getEndDepreciation($this->calculationRepository->getStartDepreciation($request['release_date']), $majorCategory->est_useful_life, strtoupper($request['depreciation_method']) == 'STL' ? strtoupper($request['depreciation_method']) : ucwords(strtolower($request['depreciation_method'])),),
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'release_date' => $request['release_date'],
            'start_depreciation' => $this->calculationRepository->getStartDepreciation($request['release_date'])
        ]);

        return $additionalCost;
    }

    public function updateAdditionalCost($request, $departmentQuery, $id)
    {

        $majorCategory = MajorCategory::withTrashed()->where('id', $request['major_category_id'])->first();
        $additionalCost = AdditionalCost::find($id);
        $additionalCost->update([
//            'fixed_asset_id' => $request['fixed_asset_id'],
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'])) ?? '-',
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request['department_id'],
            'location_id' => Location::where('sync_id', $departmentQuery->location_sync_id)->first()->id ?? null,
            'account_id' => $request['account_title_id'],
        ]);

        $additionalCost->formula()->update([
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'scrap_value' => $request['scrap_value'],
            'depreciable_basis' => $request['depreciable_basis'],
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'],
            'end_depreciation' => $this->calculationRepository->getEndDepreciation($this->calculationRepository->getStartDepreciation($request['release_date']), $majorCategory->est_useful_life, strtoupper($request['depreciation_method']) == 'STL' ? strtoupper($request['depreciation_method']) : ucwords(strtolower($request['depreciation_method'])),),
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'release_date' => $request['release_date'],
            'start_depreciation' => $this->calculationRepository->getStartDepreciation($request['release_date'])
        ]);

        return $additionalCost;
    }

    public function transformAdditionalCost($additionalCost): array
    {
        $additionalCostArr = [];

        foreach ($additionalCost as $additionalCosts) {
            $additionalCostArr [] = $this->transformSingleAdditionalCost($additionalCosts);
        }
        return $additionalCostArr;
    }

    public function transformSingleAdditionalCost($additional_cost): array
    {
        return [
            'id' => $additional_cost->id,
            'fixed_asset_id' => $additional_cost->fixed_asset_id,
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
            'voucher' => $additional_cost->voucher,
            'receipt' => $additional_cost->receipt,
            'quantity' => $additional_cost->quantity,
            'depreciation_method' => $additional_cost->depreciation_method,
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
            'care_of' => $additional_cost->care_of,
            'months_depreciated' => $additional_cost->formula->months_depreciated,
            'end_depreciation' => $additional_cost->formula->end_depreciation,
            'depreciation_per_year' => $additional_cost->formula->depreciation_per_year,
            'depreciation_per_month' => $additional_cost->formula->depreciation_per_month,
            'remaining_book_value' => $additional_cost->formula->remaining_book_value,
            'release_date' => $additional_cost->formula->release_date,
            'start_depreciation' => $additional_cost->formula->start_depreciation,
            'company' => [
                'id' => $additional_cost->department->company->id ?? '-',
                'company_code' => $additional_cost->department->company->company_code ?? '-',
                'company_name' => $additional_cost->department->company->company_name ?? '-',
            ],
            'department' => [
                'id' => $additional_cost->department->id ?? '-',
                'department_code' => $additional_cost->department->department_code ?? '-',
                'department_name' => $additional_cost->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $additional_cost->department->location->id ?? '-',
                'location_code' => $additional_cost->department->location->location_code ?? '-',
                'location_name' => $additional_cost->department->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $additional_cost->accountTitle->id ?? '-',
                'account_title_code' => $additional_cost->accountTitle->account_title_code ?? '-',
                'account_title_name' => $additional_cost->accountTitle->account_title_name ?? '-',
            ],
        ];
    }
}
