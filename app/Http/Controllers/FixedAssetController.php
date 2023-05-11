<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\MajorCategory;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function index()
    {
        $fixed_assets = FixedAsset::with('formula')->get();
        return response()->json([
            'message' => 'Fixed Assets retrieved successfully.',
            'data' => $fixed_assets
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $fixed_asset = FixedAsset::withTrashed()->find($id);
        //        return $fixed_asset->majorCategory->major_category_name;
        if (!$fixed_asset->where('id', $id)->exists()) {
            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
        }
        $fixed_asset->with('formula')->where('id', $id)->first();
        $fixed_asset_arr = [
            'id' => $fixed_asset->id,
            'capex' => $fixed_asset->capex,
            'project_name' => $fixed_asset->project_name,
            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
            'tag_number' => $fixed_asset->tag_number,
            'tag_number_old' => $fixed_asset->tag_number_old,
            'description' => $fixed_asset->description,
            'type_of_request' => $fixed_asset->type_of_request,
            'additional_description' => $fixed_asset->additional_description,
            'accountability' => $fixed_asset->accountability,
            'name' => $fixed_asset->name,
            'brand' => $fixed_asset->brand,
            'division' => $fixed_asset->division->division_name,
            'major_category' => $fixed_asset->majorCategory->major_category_name,
            'minor_category' => $fixed_asset->minorCategory->minor_category_name,
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
            'status' => $fixed_asset->is_active,
            'care_of' => $fixed_asset->care_of,
            'age' => $fixed_asset->formula->age,
            'end_depreciation' => $fixed_asset->formula->end_depreciation,
            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
            'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
            'start_depreciation' => $fixed_asset->formula->start_depreciation,
            'company_code' => $fixed_asset->company->company_code,
            'company_name' => $fixed_asset->company->company_name,
            'department_code' => $fixed_asset->department->department_code,
            'department_name' => $fixed_asset->department->department_name,
            'location_code' => $fixed_asset->location->location_code,
            'location_name' => $fixed_asset->location->location_name,
            'account_title_code' => $fixed_asset->accountTitle->account_title_code,
            'account_title_name' => $fixed_asset->accountTitle->account_title_name,
        ];
        return response()->json([
            'message' => 'Fixed Asset retrieved successfully.',
            'data' => $fixed_asset_arr
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }


    public function search(Request $request)
    {
        $search = $request->get('search');
        $limit = $request->get('limit');
        $page = $request->get('page');
        $status = $request->get('status');
        if ($status == NULL) {
            $status = 1;
        }
        if ($status == "active") {
            $status = 1;
        }
        if ($status == "deactivated") {
            $status = 0;
        }
        if ($status != "active" || $status != "deactivated") {
            $status = 1;
        }

        $fixedAsset = FixedAsset::withTrashed()->with(
            [
                'formula' => function ($query) {
                    $query->withTrashed();
                },
                'division' => function ($query) {
                    $query->withTrashed()->select('id', 'division_name');
                },
                'majorCategory' => function ($query) {
                    $query->withTrashed()->select('id', 'major_category_name');
                },
                'minorCategory' => function ($query) {
                    $query->withTrashed()->select('id', 'minor_category_name');
                },
                //                'majorCategory', 'minorCategory','division',
                //                'location', 'company', 'department', 'accountTitle',
                //                'formula'
            ]
        )
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('capex', 'LIKE', '%' . $search . '%')
                    ->orWhere('project_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('vladimir_tag_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('tag_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('tag_number_old', 'LIKE', '%' . $search . '%')
                    ->orWhere('type_of_request', 'LIKE', '%' . $search . '%')
                    ->orWhere('accountability', 'LIKE', '%' . $search . '%')
                    ->orWhere('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('brand', 'LIKE', '%' . $search . '%')
                    ->orWhere('depreciation_method', 'LIKE', '%' . $search . '%');
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->where('major_category_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('minorCategory', function ($query) use ($search) {
                    $query->where('minor_category_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('division', function ($query) use ($search) {
                    $query->where('division_name', 'LIKE', '%' . $search . '%');
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
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        $fixedAsset->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'capex' => $item->capex,
                'project_name' => $item->project_name,
                'vladimir_tag_number' => $item->vladimir_tag_number,
                'tag_number' => $item->tag_number,
                'tag_number_old' => $item->tag_number_old,
                'description' => $item->description,
                'type_of_request' => $item->type_of_request,
                'additional_description' => $item->additional_description,
                'accountability' => $item->accountability,
                'name' => $item->name,
                'brand' => $item->brand,
                'division' => $item->division->division_name,
                'major_category' => $item->majorCategory->major_category_name,
                'minor_category' => $item->minorCategory->minor_category_name,
                'voucher' => $item->voucher,
                'receipt' => $item->receipt,
                'quantity' => $item->quantity,
                'depreciation_method' => $item->depreciation_method,
                'est_useful_life' => $item->est_useful_life,
                //                    'salvage_value' => $item->salvage_value,
                'acquisition_date' => $item->acquisition_date,
                'acquisition_cost' => $item->acquisition_cost,
                'scrap_value' => $item->formula->scrap_value,
                'original_cost' => $item->formula->original_cost,
                'accumulated_cost' => $item->formula->accumulated_cost,
                'status' => $item->is_active,
                'care_of' => $item->care_of,
                'age' => $item->formula->age,
                'end_depreciation' => $item->formula->end_depreciation,
                'depreciation_per_year' => $item->formula->depreciation_per_year,
                'depreciation_per_month' => $item->formula->depreciation_per_month,
                'remaining_book_value' => $item->formula->remaining_book_value,
                'start_depreciation' => $item->formula->start_depreciation,
                'company_code' => $item->company->company_code,
                'company_name' => $item->company->company_name,
                'department_code' => $item->department->department_code,
                'department_name' => $item->department->department_name,
                'location_code' => $item->location->location_code,
                'location_name' => $item->location->location_name,
                'account_title_code' => $item->accountTitle->account_title_code,
                'account_title_name' => $item->accountTitle->account_title_name,
            ];
        });
        return $fixedAsset;
    }
}
