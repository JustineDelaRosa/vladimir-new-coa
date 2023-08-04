<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Repositories\FixedAssetExportRepository;
use App\Repositories\FixedAssetRepository;
use Illuminate\Http\Request;


class FixedAssetExportController extends Controller
{
    protected $fixedAssetRepository;

    public function __construct()
    {
        $this->fixedAssetRepository = new FixedAssetExportRepository();
    }

    public function export(Request $request)
    {
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        return $this->fixedAssetRepository->FixedAssetExport($search, $startDate, $endDate);

//        // Define the common query for fixed assets
//        $fixedAssetQuery = FixedAsset::withTrashed()->with([
//            'formula' => function ($query) {
//                $query->withTrashed();
//            },
//            'majorCategory:id,major_category_name',
//            'minorCategory:id,minor_category_name',
//        ]);
//
//        // Add date filter if both startDate and endDate are given
//        if ($startDate && $endDate) {
//            $fixedAssetQuery->whereBetween('created_at', [$startDate, $endDate]);
//        }
//
//        // Add search filter if search is given
//        if ($search) {
//            $fixedAssetQuery->where(function ($query) use ($search) {
//                $query->Where('vladimir_tag_number', 'LIKE', "%$search%")
//                    ->orWhere('tag_number', 'LIKE', "%$search%")
//                    ->orWhere('tag_number_old', 'LIKE', "%$search%")
//                    ->orWhere('accountability', 'LIKE', "%$search%")
//                    ->orWhere('accountable', 'LIKE', "%$search%")
//                    ->orWhere('brand', 'LIKE', "%$search%")
//                    ->orWhere('depreciation_method', 'LIKE', "%$search%");
//                $query->orWhereHas('subCapex', function ($query) use ($search) {
//                    $query->where('sub_capex', 'LIKE', '%' . $search . '%')
//                        ->orWhere('sub_project', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('majorCategory', function ($query) use ($search) {
//                    $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('minorCategory', function ($query) use ($search) {
//                    $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('department.division', function ($query) use ($search) {
//                    $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('assetStatus', function ($query) use ($search) {
//                    $query->where('asset_status_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('cycleCountStatus', function ($query) use ($search) {
//                    $query->where('cycle_count_status_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('depreciationStatus', function ($query) use ($search) {
//                    $query->where('depreciation_status_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('movementStatus', function ($query) use ($search) {
//                    $query->where('movement_status_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('location', function ($query) use ($search) {
//                    $query->where('location_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('company', function ($query) use ($search) {
//                    $query->where('company_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('department', function ($query) use ($search) {
//                    $query->where('department_name', 'LIKE', '%' . $search . '%');
//                });
//                $query->orWhereHas('accountTitle', function ($query) use ($search) {
//                    $query->where('account_title_name', 'LIKE', '%' . $search . '%');
//                });
//            });
//        }
//
//        // Get the fixed assets and refactor them for export
//        $fixedAssets = $fixedAssetQuery->get();
//
//        if ($fixedAssets->isEmpty()) {
//            return response()->json([
//                'message' => 'Invalid search',
//                'errors' => [
//                    'search' => [
//                        'No data found'
//                    ]
//                ]
//            ], 422);
//        }
//        return response()->json([
//            'data' => $this->refactorExport($fixedAssets),
//        ]);
    }
}
