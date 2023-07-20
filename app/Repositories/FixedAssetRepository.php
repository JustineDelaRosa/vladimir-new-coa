<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\SubCapex;

class FixedAssetRepository
{

    public function storeFixedAsset(array $request, $vladimirTagNumber, $departmentQuery)
    {
        $fixedAsset = FixedAsset::create([
            'capex_id' => SubCapex::where('id', $request->sub_capex_id)->first()->capex_id ?? null,
            'sub_capex_id' => $request->sub_capex_id ?? null,
            'vladimir_tag_number' => $vladimirTagNumber,
            'tag_number' => $request->tag_number ?? '-',
            'tag_number_old' => $request->tag_number_old ?? '-',
            'asset_description' => ($request->asset_description),
            'type_of_request_id' => $request->type_of_request_id,
            'asset_specification' => ($request->asset_specification),
            'accountability' => ($request->accountability),
            'accountable' => ($request->accountable) ?? '-',
            'cellphone_number' => $request->cellphone_number ?? '-',
            'brand' => ucwords(strtolower($request->brand)) ?? '-',
            'division_id' => $departmentQuery->division_id ?? null,
            'major_category_id' => $request->major_category_id,
            'minor_category_id' => $request->minor_category_id,
            'voucher' => $request->voucher ?? '-',
            'receipt' => $request->receipt ?? '-',
            'quantity' => $request->quantity,
            'depreciation_method' => $request->depreciation_method,
            'acquisition_date' => $request->acquisition_date,
            'acquisition_cost' => $request->acquisition_cost,
            'asset_status_id' => $request->asset_status_id,
            'depreciation_status_id' => $request->depreciation_status_id,
            'cycle_count_status_id' => $request->cycle_count_status_id,
            'movement_status_id' => $request->movement_status_id,
            'is_old_asset' => $request->is_old_asset ?? 0,
            'care_of' => ucwords(strtolower($request->care_of ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request->department_id,
            'location_id' => Location::where('sync_id', $departmentQuery->location_sync_id)->first()->id ?? null,
            'account_id' => $request->account_title_id,
        ]);

        $fixedAsset->formula()->create([
//            $this->assetCalculations($request)
            'depreciation_method' => $request->depreciation_method,
            'acquisition_date' => $request->acquisition_date,
            'acquisition_cost' => $request->acquisition_cost,
            'scrap_value' => $request->scrap_value,
            'original_cost' => $request->original_cost,
            'accumulated_cost' => $request->accumulated_cost ?? 0,
            'age' => $request->age,
            'end_depreciation' => $this->getEndDepreciation($this->getStartDepreciation($request->release_date), $request->est_useful_life),
            'depreciation_per_year' => $request->depreciation_per_year ?? 0,
            'depreciation_per_month' => $request->depreciation_per_month ?? 0,
            'remaining_book_value' => $request->remaining_book_value ?? 0,
            'release_date' => $request->release_date,
            'start_depreciation' => $this->getStartDepreciation($request->release_date)
        ]);

        return $fixedAsset;
    }

    public function searchFixedAsset($search, $limit)
    {
        return FixedAsset::withTrashed()
            ->where(function ($query) use ($search){
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
    }

}
