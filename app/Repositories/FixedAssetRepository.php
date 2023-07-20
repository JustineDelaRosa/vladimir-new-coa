<?php

namespace App\Repositories;

use App\Http\Controllers\Masterlist\FixedAssetController;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\SubCapex;

class FixedAssetRepository
{
    public function storeFixedAsset($request, $vladimirTagNumber, $departmentQuery)
    {
        $faCalculations = new FixedAssetController();
        $subCapex = SubCapex::find($request['sub_capex_id']);
        $majorCategory = MajorCategory::withTrashed()->where('id', $request['major_category_id'])->first();
        $fixedAsset = FixedAsset::create([
            'capex_id' => $subCapex ? $subCapex->capex_id : null,
            'sub_capex_id' => $subCapex->id ?? null,
            'vladimir_tag_number' => $vladimirTagNumber,
            'tag_number' => $request['tag_number'] ?? '-',
            'tag_number_old' => $request['tag_number_old'] ?? '-',
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'])) ?? '-',
            'division_id' => $departmentQuery->division_id ?? null,
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => $request['depreciation_method'],
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'is_old_asset' => $request['is_old_asset'] ?? 0,
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request['department_id'],
            'location_id' => Location::where('sync_id', $departmentQuery->location_sync_id)->first()->id ?? null,
            'account_id' => $request['account_title_id'],
        ]);

        $fixedAsset->formula()->create([
//            $this->assetCalculations($request'['']depreciation_method' => $request['depreciation_method'],
            'depreciation_method' => $request['depreciation_method'],
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'scrap_value' => $request['scrap_value'],
            'depreciable_basis' => $request['depreciable_basis'],
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'],
            'end_depreciation' => $faCalculations->getEndDepreciation($faCalculations->getStartDepreciation($request['release_date']), $majorCategory->est_useful_life),
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'release_date' => $request['release_date'],
            'start_depreciation' => $faCalculations->getStartDepreciation($request['release_date'])
        ]);

        return $fixedAsset;
    }

    public function updateFixedAsset($request, $departmentQuery)
    {
        $faCalculations = new FixedAssetController();
        $subCapex = SubCapex::find($request['sub_capex_id']);
        $majorCategory = MajorCategory::withTrashed()->where('id', $request['major_category_id'])->first();
        $fixedAsset = FixedAsset::create([
            'capex_id' => $subCapex ? $subCapex->capex_id : null,
            'sub_capex_id' => $subCapex->id ?? null,
            'tag_number' => $request['tag_number'] ?? '-',
            'tag_number_old' => $request['tag_number_old'] ?? '-',
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'])) ?? '-',
            'division_id' => $departmentQuery->division_id ?? null,
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => $request['depreciation_method'],
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'is_old_asset' => $request['is_old_asset'] ?? 0,
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request['department_id'],
            'location_id' => Location::where('sync_id', $departmentQuery->location_sync_id)->first()->id ?? null,
            'account_id' => $request['account_title_id'],
        ]);

        $fixedAsset->formula()->create([
//            $this->assetCalculations($request'['']depreciation_method' => $request['depreciation_method'],
            'depreciation_method' => $request['depreciation_method'],
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'scrap_value' => $request['scrap_value'],
            'depreciable_basis' => $request['depreciable_basis'],
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'],
            'end_depreciation' => $faCalculations->getEndDepreciation($faCalculations->getStartDepreciation($request['release_date']), $majorCategory->est_useful_life),
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'release_date' => $request['release_date'],
            'start_depreciation' => $faCalculations->getStartDepreciation($request['release_date'])
        ]);

        return $fixedAsset;
    }

    public function searchFixedAsset($search, $limit)
    {
        $fixedAsset = FixedAsset::withTrashed()
            ->where(function ($query) use ($search) {
                $query->Where('vladimir_tag_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('tag_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('tag_number_old', 'LIKE', '%' . $search . '%')
                    ->orWhere('type_of_request_id', 'LIKE', '%' . $search . '%')
                    ->orWhere('accountability', 'LIKE', '%' . $search . '%')
                    ->orWhere('accountable', 'LIKE', '%' . $search . '%')
                    ->orWhere('brand', 'LIKE', '%' . $search . '%')
                    ->orWhere('depreciation_method', 'LIKE', '%' . $search . '%');
                $query->orWhereHas('subCapex', function ($query) use ($search) {
                    $query->where('sub_capex', 'LIKE', '%' . $search . '%')
                        ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('minorCategory', function ($query) use ($search) {
                    $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('division', function ($query) use ($search) {
                    $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('assetStatus', function ($query) use ($search) {
                    $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('cycleCountStatus', function ($query) use ($search) {
                    $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('depreciationStatus', function ($query) use ($search) {
                    $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('movementStatus', function ($query) use ($search) {
                    $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('location', function ($query) use ($search) {
                    $query->where('location_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('company', function ($query) use ($search) {
                    $query->where('company_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('department', function ($query) use ($search) {
                    $query->where('department_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('accountTitle', function ($query) use ($search) {
                    $query->where('account_title_name', 'LIKE', '%' . $search . '%');
                });
            })
            ->orderBy('id', 'DESC')
            ->paginate($limit);

        $fixedAsset->getCollection()->transform(function ($item) {
            return $this->transformSingleFixedAsset($item);
        });

        return $fixedAsset;
    }

    public function transformFixedAsset($fixed_asset): array
    {
        $fixed_assets_arr = [];
        foreach ($fixed_asset as $asset) {
            // Transform the current asset using the transformSingleFixedAsset method
            $fixed_assets_arr [] = $this->transformSingleFixedAsset($asset);
        }
        return $fixed_assets_arr;
    }
    public function transformSingleFixedAsset($fixed_asset):array
    {
        return [
            'id' => $fixed_asset->id,
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
            'tag_number' => $fixed_asset->tag_number,
            'tag_number_old' => $fixed_asset->tag_number_old,
            'asset_description' => $fixed_asset->asset_description,
            'type_of_request' => [
                'id' => $fixed_asset->typeOfRequest->id ?? '-',
                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $fixed_asset->asset_specification,
            'accountability' => $fixed_asset->accountability,
            'accountable' => $fixed_asset->accountable,
            'cellphone_number' => $fixed_asset->cellphone_number,
            'brand' => $fixed_asset->brand,
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
            'voucher' => $fixed_asset->voucher,
            'receipt' => $fixed_asset->receipt,
            'quantity' => $fixed_asset->quantity,
            'depreciation_method' => $fixed_asset->depreciation_method,
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date,
            'acquisition_cost' => $fixed_asset->acquisition_cost,
            'scrap_value' => $fixed_asset->formula->scrap_value,
            'depreciable_basis' => $fixed_asset->formula->depreciable_basis,
            'accumulated_cost' => $fixed_asset->formula->accumulated_cost,
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
            'is_old_asset' => $fixed_asset->is_old_asset,
            'care_of' => $fixed_asset->care_of,
            'months_depreciated' => $fixed_asset->formula->months_depreciated,
            'end_depreciation' => $fixed_asset->formula->end_depreciation,
            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
            'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
            'release_date' => $fixed_asset->formula->release_date,
            'start_depreciation' => $fixed_asset->formula->start_depreciation,
            'company' => [
                'id' => $fixed_asset->department->company->id ?? '-',
                'company_code' => $fixed_asset->department->company->company_code ?? '-',
                'company_name' => $fixed_asset->department->company->company_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->department->location->id ?? '-',
                'location_code' => $fixed_asset->department->location->location_code ?? '-',
                'location_name' => $fixed_asset->department->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $fixed_asset->accountTitle->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->account_title_name ?? '-',
            ],
        ];
    }
}
//return [
//            'id' => $fixed_asset->id,
//            'capex' => [
//                'id' => $fixed_asset->capex->id ?? '-',
//                'capex' => $fixed_asset->capex->capex ?? '-',
//                'project_name' => $fixed_asset->capex->project_name ?? '-',
//            ],
//            'sub_capex' => [
//                'id' => $fixed_asset->subCapex->id ?? '-',
//                'sub_capex' => $fixed_asset->subCapex->sub_capex ?? '-',
//                'sub_project' => $fixed_asset->subCapex->sub_project ?? '-',
//            ],
//            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
//            'tag_number' => $fixed_asset->tag_number,
//            'tag_number_old' => $fixed_asset->tag_number_old,
//            'asset_description' => $fixed_asset->asset_description,
//            'type_of_request' => [
//                'id' => $fixed_asset->typeOfRequest->id ?? '-',
//                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name ?? '-',
//            ],
//            'asset_specification' => $fixed_asset->asset_specification,
//            'accountability' => $fixed_asset->accountability,
//            'accountable' => $fixed_asset->accountable,
//            'cellphone_number' => $fixed_asset->cellphone_number,
//            'brand' => $fixed_asset->brand,
//            'division' => [
//                'id' => $fixed_asset->department->division->id ?? '-',
//                'division_name' => $fixed_asset->department->division->division_name ?? '-',
//            ],
//            'major_category' => [
//                'id' => $fixed_asset->majorCategory->id ?? '-',
//                'major_category_name' => $fixed_asset->majorCategory->major_category_name ?? '-',
//            ],
//            'minor_category' => [
//                'id' => $fixed_asset->minorCategory->id ?? '-',
//                'minor_category_name' => $fixed_asset->minorCategory->minor_category_name ?? '-',
//            ],
//            'est_useful_life' => $fixed_asset->majorCategory->est_useful_life ?? '-',
//            'voucher' => $fixed_asset->voucher,
//            'receipt' => $fixed_asset->receipt,
//            'quantity' => $fixed_asset->quantity,
//            'depreciation_method' => $fixed_asset->depreciation_method,
//            //                    'salvage_value' => $fixed_asset->salvage_value,
//            'acquisition_date' => $fixed_asset->acquisition_date,
//            'acquisition_cost' => $fixed_asset->acquisition_cost,
//            'scrap_value' => $fixed_asset->formula->scrap_value,
//            'depreciable_basis' => $fixed_asset->formula->depreciable_basis,
//            'accumulated_cost' => $fixed_asset->formula->accumulated_cost,
//            'asset_status' => [
//                'id' => $fixed_asset->assetStatus->id ?? '-',
//                'asset_status_name' => $fixed_asset->assetStatus->asset_status_name ?? '-',
//            ],
//            'cycle_count_status' => [
//                'id' => $fixed_asset->cycleCountStatus->id ?? '-',
//                'cycle_count_status_name' => $fixed_asset->cycleCountStatus->cycle_count_status_name ?? '-',
//            ],
//            'depreciation_status' => [
//                'id' => $fixed_asset->depreciationStatus->id ?? '-',
//                'depreciation_status_name' => $fixed_asset->depreciationStatus->depreciation_status_name ?? '-',
//            ],
//            'movement_status' => [
//                'id' => $fixed_asset->movementStatus->id ?? '-',
//                'movement_status_name' => $fixed_asset->movementStatus->movement_status_name ?? '-',
//            ],
//            'is_old_asset' => $fixed_asset->is_old_asset,
//            'care_of' => $fixed_asset->care_of,
//            'months_depreciated' => $fixed_asset->formula->months_depreciated,
//            'end_depreciation' => $fixed_asset->formula->end_depreciation,
//            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
//            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
//            'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
//            'release_date' => $fixed_asset->formula->release_date,
//            'start_depreciation' => $fixed_asset->formula->start_depreciation,
//            'company' => [
//                'id' => $fixed_asset->department->company->id ?? '-',
//                'company_code' => $fixed_asset->department->company->company_code ?? '-',
//                'company_name' => $fixed_asset->department->company->company_name ?? '-',
//            ],
//            'department' => [
//                'id' => $fixed_asset->department->id ?? '-',
//                'department_code' => $fixed_asset->department->department_code ?? '-',
//                'department_name' => $fixed_asset->department->department_name ?? '-',
//            ],
//            'location' => [
//                'id' => $fixed_asset->department->location->id ?? '-',
//                'location_code' => $fixed_asset->department->location->location_code ?? '-',
//                'location_name' => $fixed_asset->department->location->location_name ?? '-',
//            ],
//            'account_title' => [
//                'id' => $fixed_asset->accountTitle->id ?? '-',
//                'account_title_code' => $fixed_asset->accountTitle->account_title_code ?? '-',
//                'account_title_name' => $fixed_asset->accountTitle->account_title_name ?? '-',
//            ],
//        ];
