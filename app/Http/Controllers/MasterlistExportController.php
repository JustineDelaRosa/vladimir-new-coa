<?php

namespace App\Http\Controllers;

use App\Exports\MasterlistExport;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;


class MasterlistExportController extends Controller
{
    public function export(Request $request)
    {
//        $validated = $request->validate([
//            'startDate' => 'nullable|date',
//            'endDate' => 'nullable|date',
//        ]);
//        $filename = $request->get('filename');
//        //ternary if empty the default filename is Fixed_Asset_Date
//        $filename = $filename == null ? 'Fixed_Asset'. '_' . date('Y-m-d') :
//            str_replace(' ', '_', $filename) . '_' . date('Y-m-d');
//        $search = $request->get('search');
//        $startDate = $request->get('startDate');
//        $endDate = $request->get('endDate');
//
//        //directly download the Excel file to the frontend without saving it to the storage folder
//        return Excel::download(new MasterlistExport($search, $startDate, $endDate), $filename . '.xlsx');
//        return Excel::download(new MasterlistExport($search, $startDate, $endDate), $filename . '.xlsx');
//        return (new MasterlistExport($search, $startDate, $endDate))->download($filename . '.xlsx');


        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
//      $result = [];



        if($startDate != null && $endDate != null && $search == null){
            $fixedAsset = FixedAsset::whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('id', 'ASC')->get();
//                ->select('vladimir_tag_number', 'asset_description','id')
//                ->chunk(500, function ($assets) use (&$result) {
//                    foreach ($assets as $asset) {
//                        $result[] = [
//                            'vladimir_tag_number' => $asset->vladimir_tag_number,
//                            'asset_description' => $asset->asset_description,
//                        ];
//                    }
//                });
            return $this->refactorExport($fixedAsset);
        }

        if (strpos($search, ',') !== false || strlen($search) < 2) {
            $search = explode(',', $search);
            $fixedAsset = FixedAsset::whereIn('type_of_request_id', $search)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('id', 'ASC')->get();
//                ->select('vladimir_tag_number', 'asset_description','id','type_of_request_id')
//                ->chunk(500, function ($assets) use (&$result) {
//                    foreach ($assets as $asset) {
//                        $result[] = [
//                            'vladimir_tag_number' => $asset->vladimir_tag_number,
//                            'asset_description' => $asset->asset_description,
//                            'id' => $asset->id,
//                            'type_of_request_id' => $asset->type_of_request_id,
//                        ];
//                    }
//                });
            return $this->refactorExport($fixedAsset);
        }


        $fixedAsset = FixedAsset::where(function ($query) use ($search) {
            $query->Where('vladimir_tag_number', $search )
                ->orWhere('tag_number', $search);
        })->orderBy('id', 'ASC')->get();
//            ->select('vladimir_tag_number', 'asset_description','id')
//            ->chunk(500, function ($assets) use (&$result) {
//                foreach ($assets as $asset) {
//                    $result[] = [
//                        'vladimir_tag_number' => $asset->vladimir_tag_number,
//                        'asset_description' => $asset->asset_description,
//                    ];
//                }
//            });

        return $this->refactorExport($fixedAsset);
    }


////        $filename = $request->get('filename');
////        //ternary if empty, the default filename is Fixed_Asset_Date
////        $filename = $filename == null ? 'Fixed_Asset'. '_' . date('Y-m-d') :
////                    str_replace(' ', '_', $filename) . '_' . date('Y-m-d');
//        $search = $request->get('search');
//        $startDate = $request->get('startDate');
//        $endDate = $request->get('endDate');
//        $faStatus = $request->get('faStatus');
//        if ($faStatus === null) {
//            $faStatus = ['Good', 'For Disposal', 'For Repair', 'Spare', 'Sold', 'Write Off'];
//        } elseif($faStatus == 'Disposed, Sold'){
//            $faStatus = ['Disposed', 'Sold'];
//        }else {
//            $faStatus = array_filter(array_map('trim', explode(',', $faStatus)), function ($status) {
//                return $status !== 'Disposed';
//            });
//        }
//
//        if($startDate != null && $endDate != null){
//            $fixed_assets = FixedAsset::withTrashed()->with([
//                    'formula' => function ($query) {
//                        $query->withTrashed();
//                    },
//                    'division' => function ($query) {
//                        $query->withTrashed()->select('id', 'division_name');
//                    },
//                    'majorCategory' => function ($query) {
//                        $query->withTrashed()->select('id', 'major_category_name');
//                    },
//                    'minorCategory' => function ($query) {
//                        $query->withTrashed()->select('id', 'minor_category_name');
//                    },
//                ])
//                ->where(function ($query) use ($faStatus) {
//                    $query->whereIn('faStatus', $faStatus);
//                })
//                ->whereBetween('created_at', [$startDate, $endDate])
//                ->get();
//            return $this->refactorExport($fixed_assets);
//        }
//
//        $fixedAsset = FixedAsset::withTrashed()->with([
//                'formula' => function ($query) {
//                    $query->withTrashed();
//                },
//                'division' => function ($query) {
//                    $query->withTrashed()->select('id', 'division_name');
//                },
//                'majorCategory' => function ($query) {
//                    $query->withTrashed()->select('id', 'major_category_name');
//                },
//                'minorCategory' => function ($query) {
//                    $query->withTrashed()->select('id', 'minor_category_name');
//                },
//            ])
//            ->Where(function ($query) use ($search, $startDate, $endDate) {
//                $query->Where('project_name', 'LIKE', '%'.$search.'%')
//                    ->orWhere('vladimir_tag_number', 'LIKE', '%'.$search.'%')
//                    ->orWhere('tag_number', 'LIKE', '%'.$search.'%')
//                    ->orWhere('tag_number_old', 'LIKE', '%'.$search.'%')
//                    ->orWhere('accountability', 'LIKE', '%'.$search.'%')
//                    ->orWhere('accountable', 'LIKE', '%'.$search.'%')
//                    ->orWhere('brand', 'LIKE', '%'.$search.'%')
//                    ->orWhere('depreciation_method', 'LIKE', '%'.$search.'%');
//                $query->orWhereHas('majorCategory', function ($query) use ($search) {
//                    $query->where('major_category_name', 'LIKE', '%'.$search.'%');
//                });
//                $query->orWhereHas('minorCategory', function ($query) use ($search) {
//                    $query->where('minor_category_name', 'LIKE','%'.$search.'%');
//                });
//                $query->orWhereHas('division', function ($query) use ($search) {
//                    $query->where('division_name', 'LIKE', '%{$search}%');
//                });
//                $query->orWhereHas('location', function ($query) use ($search) {
//                    $query->where('location_name', 'LIKE', '%'.$search.'%');
//                });
//                $query->orWhereHas('company', function ($query) use ($search) {
//                    $query->where('company_name', 'LIKE', '%'.$search.'%');
//                });
//                $query->orWhereHas('department', function ($query) use ($search) {
//                    $query->where('department_name', 'LIKE', '%'.$search.'%');
//                });
//                $query->orWhereHas('accountTitle', function ($query) use ($search) {
//                    $query->where('account_title_name', 'LIKE', '%'.$search.'%');
//                });
//                $query->orWhereHas('typeOfRequest', function ($query) use ($search) {
//                    $query->where('type_of_request_name', 'LIKE', '%'.$search.'%');
//                });
//            })
//            ->where(function ($query) use ($faStatus) {
//                //array of status or not array
//                if (is_array($faStatus)) {
//                    $query->whereIn('faStatus', $faStatus);
//                } else {
//                    $query->where('faStatus', $faStatus);
//                }
//            })
//            ->orderBy('id', 'ASC')
//            ->get();
//
//        if($fixedAsset->count() == 0){
//            return response()->json([
//                'message' => 'No data found',
//            ], 404);
//        }
//        return $this->refactorExport($fixedAsset);


    public function refactorExport($fixedAssets): array
    {
        $fixed_assets_arr = [];
        foreach ($fixedAssets as $fixed_asset) {
            $fixed_assets_arr[] = [
                'id' => $fixed_asset->id,
                'capex' => $fixed_asset->capex->capex ?? '-',
                'project_name' => $fixed_asset->project_name,
                'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
                'tag_number' => $fixed_asset->tag_number,
                'tag_number_old' => $fixed_asset->tag_number_old,
                'asset_description' => $fixed_asset->asset_description,
                'type_of_request' => $fixed_asset->typeOfRequest->type_of_request_name,
                'asset_specification' => $fixed_asset->asset_specification,
                'accountability' => $fixed_asset->accountability,
                'accountable' => $fixed_asset->accountable,
                'brand' => $fixed_asset->brand,
                'division' => $fixed_asset->division->division_name,
                'major_category' => $fixed_asset->majorCategory->major_category_name,
                'minor_category' => $fixed_asset->minorCategory->minor_category_name,
                'capitalized' => $fixed_asset->capitalized,
                'voucher' => $fixed_asset->voucher,
                'receipt' => $fixed_asset->receipt,
                'quantity' => $fixed_asset->quantity,
                'depreciation_method' => $fixed_asset->depreciation_method,
                'est_useful_life' => $fixed_asset->est_useful_life,
                //                    'salvage_value' => $fixed_asset->salvage_value,
                'acquisition_date' => $fixed_asset->acquisition_date,
                'acquisition_cost' => $fixed_asset->acquisition_cost,
                'scrap_value' => $fixed_asset->formula->scrap_value,
                'original_cost' => $fixed_asset->formula->original_cost,
                'accumulated_cost' => $fixed_asset->formula->accumulated_cost,
                'faStatus' => $fixed_asset->faStatus,
                'care_of' => $fixed_asset->care_of,
                'age' => $fixed_asset->formula->age,
                'end_depreciation' => $fixed_asset->formula->end_depreciation,
                'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
                'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
                'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
                'released_date' => $fixed_asset->formula->released_date,
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
