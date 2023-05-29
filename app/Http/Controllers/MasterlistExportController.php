<?php

namespace App\Http\Controllers;

use App\Exports\MasterlistExport;
use App\Models\FixedAsset;
use Illuminate\Http\Request;

class MasterlistExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
        ]);
        $filename = $request->get('filename');
        //ternary if empty, the default filename is Fixed_Asset_Date
        $filename = $filename == null ? 'Fixed_Asset'. '_' . date('Y-m-d') :
                    str_replace(' ', '_', $filename) . '_' . date('Y-m-d');
        $search = $request->get('search');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        $fixedAsset = FixedAsset::query()
            ->with([
                'formula'=>function($query){
                    $query->withTrashed();
                },
                'majorCategory'=>function($query){
                    $query->withTrashed();
                },
                'minorCategory'=>function($query){
                    $query->withTrashed();
                },
                'division'=>function($query){
                    $query->withTrashed();
                },
            ])
            ->when($search, function ($query, $search) {
                return  $query->where('capex',$search)
                    ->orWhere('project_name',$search)
                    ->orWhere('vladimir_tag_number',$search)
                    ->orWhere('tag_number',$search)
                    ->orWhere('tag_number_old',$search)
                    //->orWhere('asset_description',$search)
                    ->orWhere('type_of_request',$search)
                    ->orWhere('accountability',$search)
                    ->orWhere('accountable',$search)
                    ->orWhere('brand',$search)
                    ->orWhere('depreciation_method',$search)
                    ->orWhereHas('majorCategory', function ($query) use ($search) {
                        $query->withTrashed()->where('major_category_name', $search);
                    })
                    ->orWhereHas('minorCategory', function ($query) use ($search) {
                        $query->withTrashed()->where('minor_category_name',  $search );
                    })
                    ->orWhereHas('division', function ($query) use ($search) {
                        $query->withTrashed()->where('division_name',  $search);
                    })
                    ->orWhereHas('company', function ($query) use ($search) {
                        $query->where('company_name', $search);
                    })
                    ->orWhereHas('department', function ($query) use ($search) {
                        $query->where('department_name', $search );
                    })
                    ->orWhereHas('location', function ($query) use ($search) {
                        $query->where('location_name', $search);
                    })
                    ->orWhereHas('accountTitle', function ($query) use ($search) {
                        $query->where('account_title_name', $search);
                    });

            })
            ->withTrashed()
            ->when($startDate, function ($query, $startDate) {
                return $query->where('created_at', '>=', $startDate);
            })
            ->when($endDate, function ($query, $endDate) {
                return $query->where('created_at', '<=', $endDate);
            })
            ->orderBy('id', 'ASC');

        if($fixedAsset->count() == 0){
            return response()->json([
                'message' => 'No data found'
            ], 404 );
        }

        $export = (new MasterlistExport($search, $startDate, $endDate))->download($filename . '.xlsx');

          return $export;

    }
}
