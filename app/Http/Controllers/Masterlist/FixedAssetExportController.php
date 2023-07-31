<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Illuminate\Http\Request;


class FixedAssetExportController extends Controller
{
    public function export(Request $request)
    {
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

// Define the common query for fixed assets
        $fixedAssetQuery = FixedAsset::withTrashed()->with([
            'formula' => function ($query) {
                $query->withTrashed();
            },
            'majorCategory:id,major_category_name',
            'minorCategory:id,minor_category_name',
        ]);

// Add date filter if both startDate and endDate are given
        if ($startDate && $endDate) {
            $fixedAssetQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

// Add search filter if search is given
        if ($search) {
            $fixedAssetQuery->where(function ($query) use ($search) {
                $query->Where('vladimir_tag_number', 'LIKE', "%$search%")
                    ->orWhere('tag_number', 'LIKE', "%$search%")
                    ->orWhere('tag_number_old', 'LIKE', "%$search%")
                    ->orWhere('accountability', 'LIKE', "%$search%")
                    ->orWhere('accountable', 'LIKE', "%$search%")
                    ->orWhere('brand', 'LIKE', "%$search%")
                    ->orWhere('depreciation_method', 'LIKE', "%$search%");
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
                $query->orWhereHas('department.division', function ($query) use ($search) {
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
            });
        }

        // Get the fixed assets and refactor them for export
        $fixedAssets = $fixedAssetQuery->get();

        if ($fixedAssets->isEmpty()) {
            return response()->json([
                'message' => 'Invalid search',
                'errors' => [
                    'search' => [
                        'No data found'
                    ]
                ]
            ], 422);
        }
        return response()->json([
            'data' => $this->refactorExport($fixedAssets),
        ]);
    }

    public function refactorExport($fixedAssets): array
    {
        $fixed_assets_arr = [];
        foreach ($fixedAssets as $fixed_asset) {
            $fixed_assets_arr[] = [
                'id' => $fixed_asset->id,
                'capex' => $fixed_asset->capex->capex ?? '-',
                'project_name' => $fixed_asset->capex->project_name ?? '-',
                'sub_capex' => $fixed_asset->subCapex->sub_capex ?? '-',
                'sub_project' => $fixed_asset->subCapex->sub_project ?? '-',
                'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
                'tag_number' => $fixed_asset->tag_number,
                'tag_number_old' => $fixed_asset->tag_number_old,
                'asset_description' => $fixed_asset->asset_description,
                'type_of_request' => $fixed_asset->typeOfRequest->type_of_request_name,
                'asset_specification' => $fixed_asset->asset_specification,
                'accountability' => $fixed_asset->accountability,
                'accountable' => $fixed_asset->accountable,
                'brand' => $fixed_asset->brand,
                'division' => $fixed_asset->department->division->division_name,
                'major_category' => $fixed_asset->majorCategory->major_category_name,
                'minor_category' => $fixed_asset->minorCategory->minor_category_name,
                'capitalized' => $fixed_asset->capitalized,
                'cellphone_number' => $fixed_asset->cellphone_number,
                'voucher' => $fixed_asset->voucher,
                'receipt' => $fixed_asset->receipt,
                'quantity' => $fixed_asset->quantity,
                'depreciation_method' => $fixed_asset->depreciation_method,
                'est_useful_life' => $fixed_asset->MajorCategory->est_useful_life,
                //                    'salvage_value' => $fixed_asset->salvage_value,
                'acquisition_date' => $fixed_asset->acquisition_date,
                'acquisition_cost' => $fixed_asset->acquisition_cost,
                'scrap_value' => $fixed_asset->formula->scrap_value,
                'depreciable_basis' => $fixed_asset->formula->depreciable_basis,
                'accumulated_cost' => $fixed_asset->formula->accumulated_cost,
                'asset_status' => $fixed_asset->assetStatus->asset_status_name,
                'cycle_count_status' => $fixed_asset->cycleCountStatus->cycle_count_status_name,
                'depreciation_status' => $fixed_asset->depreciationStatus->depreciation_status_name,
                'movement_status' => $fixed_asset->movementStatus->movement_status_name,
                'care_of' => $fixed_asset->care_of,
                'months_depreciated' => $fixed_asset->formula->months_depreciated,
                'end_depreciation' => $fixed_asset->formula->end_depreciation,
                'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
                'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
                'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
                'release_date' => $fixed_asset->formula->release_date,
                'start_depreciation' => $fixed_asset->formula->start_depreciation,
                'company_code' => $fixed_asset->company->company_code,
                'company_name' => $fixed_asset->company->company_name,
                'department_code' => $fixed_asset->department->department_code,
                'department_name' => $fixed_asset->department->department_name,
                'location_code' => $fixed_asset->location->location_code,
                'location_name' => $fixed_asset->location->location_name,
                'account_title_code' => $fixed_asset->accountTitle->account_title_code,
                'account_title_name' => $fixed_asset->accountTitle->account_title_name
            ];
        }
        return $fixed_assets_arr;
    }
}
