<?php

namespace App\Http\Controllers;

use App\Exports\MasterlistExport;
use App\Http\Requests\FixedAsset\FixedAssetRequest;
use App\Imports\MasterlistImport;
use App\Models\AccountTitle;
use App\Models\Company;
use App\Models\Department;
use App\Models\Division;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
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
//        //transform collection to array
//        $fixed_assets_arr = [];
//        foreach ($fixed_assets as $fixed_asset) {
//            $fixed_assets_arr[] = [
//                'id' => $fixed_asset->id,
//                'capex' => $fixed_asset->capex,
//                'project_name' => $fixed_asset->project_name,
//                'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
//                'tag_number' => $fixed_asset->tag_number,
//                'tag_number_old' => $fixed_asset->tag_number_old,
//                'asset_description' => $fixed_asset->asset_description,
//                'type_of_request' => $fixed_asset->type_of_request,
//                'asset_specification' => $fixed_asset->asset_specification,
//                'accountability' => $fixed_asset->accountability,
//                'accountable' => $fixed_asset->accountable,
//                'brand' => $fixed_asset->brand,
//                'division' => $fixed_asset->division->division_name,
//                'major_category' => $fixed_asset->majorCategory->major_category_name,
//                'minor_category' => $fixed_asset->minorCategory->minor_category_name,
//                'voucher' => $fixed_asset->voucher,
//                'receipt' => $fixed_asset->receipt,
//                'quantity' => $fixed_asset->quantity,
//                'depreciation_method' => $fixed_asset->depreciation_method,
//                'est_useful_life' => $fixed_asset->est_useful_life,
//                //                    'salvage_value' => $fixed_asset->salvage_value,
//                'acquisition_date' => $fixed_asset->acquisition_date,
//                'acquisition_cost' => $fixed_asset->acquisition_cost,
//                'scrap_value' => $fixed_asset->formula->scrap_value,
//                'original_cost' => $fixed_asset->formula->original_cost,
//                'accumulated_cost' => $fixed_asset->formula->accumulated_cost,
//                'status' => $fixed_asset->is_active,
//                'care_of' => $fixed_asset->care_of,
//                'age' => $fixed_asset->formula->age,
//                'end_depreciation' => $fixed_asset->formula->end_depreciation,
//                'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
//                'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
//                'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
//                'start_depreciation' => $fixed_asset->formula->start_depreciation,
//                'company_code' => $fixed_asset->company->company_code,
//                'company_name' => $fixed_asset->company->company_name,
//                'department_code' => $fixed_asset->department->department_code,
//                'department_name' => $fixed_asset->department->department_name,
//                'location_code' => $fixed_asset->location->location_code,
//                'location_name' => $fixed_asset->location->location_name,
//                'account_title_code' => $fixed_asset->accountTitle->account_title_code,
//                'account_title_name' => $fixed_asset->accountTitle->account_title_name
//            ];
//        }
//        return response()->json([
//            'message' => 'Fixed Assets retrieved successfully.',
//            'data' => $fixed_assets_arr
//        ], 200);


        return response()->json([
            'message' => 'Fixed Assets retrieved successfully.',
            'data' => $fixed_assets
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(FixedAssetRequest $request)
    {
        $vladimirTagNumber = (new MasterlistImport())->vladimirTagGenerator();
        if(!is_numeric($vladimirTagNumber) || strlen($vladimirTagNumber) != 13) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['Wrong vladimir tag number format. Please try again.']
            ], 422);
        }
        //Major Category check
        $majorCategoryCheck = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->where('division_id', $request->division_id)->exists();
        if(!$majorCategoryCheck) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'major_category' => [
                            'The major category does not match the division.'
                        ]
                    ]
                ],
                422
            );
        }

        //minor Category check
        $majorCategory = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->where('division_id', $request->division_id)->first()->id;
        $minorCategoryCheck = MinorCategory::withTrashed()->where('id', $request->minor_category_id)
            ->where('major_category_id',$majorCategory)->exists();
        if ($request->status != 'Disposed') {
            //check minor catrgory if softDelete
            if ( MinorCategory::onlyTrashed()->where('id', $request->minor_category_id)
                ->where('major_category_id',$majorCategory)->exists()) {
                return response()->json(
                    [
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'minor_category' => [
                                'Conflict with minor category and fixed asset status.'
                            ]
                        ]
                    ],
                    422
                );
            }
        }
        if(!$minorCategoryCheck) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'minor_category' => [
                            'The minor category does not match the major category.'
                        ]
                    ]
                ],
                422
            );
        }

        $fixedAsset = FixedAsset::create([
                'capex' => $request->capex ?? '-',
                'project_name' => $request->project_name ?? '-',
                'vladimir_tag_number' => $vladimirTagNumber,
                'tag_number' => $request->tag_number ?? '-',
                'tag_number_old' => $request->tag_number_old ?? '-',
                'asset_description' => $request->asset_description,
                'type_of_request' => $request->type_of_request,
                'asset_specification' => $request->asset_specification,
                'accountability' => $request->accountability,
                'accountable' => $request->accountable,
                'cellphone_number' => $request->cellphone_number ?? '-',
                'brand' => $request->brand ?? '-',
                'division_id' => $request->division_id,
                'major_category_id' => $request->major_category_id,
                'minor_category_id' => $request->minor_category_id,
                'voucher' => $request->voucher ?? '-',
                'receipt' => $request->receipt ?? '-',
                'quantity' => $request->quantity,
                'depreciation_method' => $request->depreciation_method,
                'est_useful_life' => $request->est_useful_life,
                'acquisition_date' => $request->acquisition_date, //TODO:
                'acquisition_cost' => $request->acquisition_cost,
                'status' => $request->status,
                'is_old_asset' => $request->is_old_asset ?? 0,
                'care_of' => $request->care_of,
                'company_id' => $request->company_id,
                'company_name' => Company::where('id', $request->company_id)->value('company_name'),
                'department_id' => $request->department_id,
                'department_name' => Department::where('id', $request->department_id)->value('department_name'),
                'location_id' => $request->location_id,
                'location_name' => Location::where('id', $request->location_id)->value('location_name'),
                'account_id' => $request->account_title_id,
                'account_title' => AccountTitle::where('id', $request->account_title_id)->value('account_title_name'),
            ]);

        $fixedAsset->formula()->create([
            'depreciation_method' => $request->depreciation_method,
            'est_useful_life' => $request->est_useful_life,
            'acquisition_date' => $request->acquisition_date,
            'acquisition_cost' => $request->acquisition_cost,
            'scrap_value' => $request->scrap_value,
            'original_cost' => $request->original_cost,
            'accumulated_cost' => $request->accumulated_cost,
            'age' => $request->age,
            'end_depreciation' => $request->end_depreciation,
            'depreciation_per_year' => $request->depreciation_per_year,
            'depreciation_per_month' => $request->depreciation_per_month,
            'remaining_book_value' => $request->remaining_book_value,
            'start_depreciation' => $request->start_depreciation,
        ]);

        if($request->status == 'Disposed')
        {
            $fixedAsset->delete();
            $fixedAsset->formula()->delete();
            return response()->json([
                'message' => 'Fixed Asset created successfully, but disposed immediately.',
                'data' => $fixedAsset->withTrashed()->with([
                    'formula' => function($query) {
                        $query->withTrashed();
                    }]
                )->where('id', $fixedAsset->id)->first()
            ], 201);
        }

        //return the fixed asset and formula
        return response()->json([
            'message' => 'Fixed Asset created successfully.',
            'data' => $fixedAsset->with('formula')->where('id', $fixedAsset->id)->first()
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $fixed_asset = FixedAsset::where('id', $id)->first();
        //        return $fixed_asset->majorCategory->major_category_name;
        if (!$fixed_asset) {
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
                'asset_description' => $fixed_asset->asset_description,
                'type_of_request' => $fixed_asset->type_of_request,
                'asset_specification' => $fixed_asset->asset_specification,
                'accountability' => $fixed_asset->accountability,
                'accountable' => $fixed_asset->accountable,
                'cellphone_number' => $fixed_asset->cellphone_number,
                'brand' => $fixed_asset->brand,
                'division' => [
                    'id' => $fixed_asset->division->id,
                    'division_name' => $fixed_asset->division->division_name,
                ],
                'major_category' => [
                    'id' => $fixed_asset->majorCategory->id,
                    'major_category_name' => $fixed_asset->majorCategory->major_category_name,
                ],
                'minor_category' => [
                    'id' => $fixed_asset->minorCategory->id,
                    'minor_category_name' => $fixed_asset->minorCategory->minor_category_name,
                ],
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
                'status' => $fixed_asset->status,
                'is_old_asset' => $fixed_asset->is_old_asset,
                'care_of' => $fixed_asset->care_of,
                'age' => $fixed_asset->formula->age,
                'end_depreciation' => $fixed_asset->formula->end_depreciation,
                'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
                'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
                'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
                'start_depreciation' => $fixed_asset->formula->start_depreciation,
                'company' => [
                    'id' => $fixed_asset->company->id,
                    'company_code' => $fixed_asset->company->company_code,
                    'company_name' => $fixed_asset->company->company_name,
                ],
                'department' => [
                    'id' => $fixed_asset->department->id,
                    'department_code' => $fixed_asset->department->department_code,
                    'department_name' => $fixed_asset->department->department_name,
                ],
                'location' => [
                    'id' => $fixed_asset->location->id,
                    'location_code' => $fixed_asset->location->location_code,
                    'location_name' => $fixed_asset->location->location_name,
                ],
                'account_title' => [
                    'id' => $fixed_asset->accountTitle->id,
                    'account_title_code' => $fixed_asset->accountTitle->account_title_code,
                    'account_title_name' => $fixed_asset->accountTitle->account_title_name,
                ],
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(FixedAssetRequest $request, int $id)
    {
        $request->validated();

        //Major Category check
        $majorCategoryCheck = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->where('division_id', $request->division_id)->exists();
        if(!$majorCategoryCheck) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'major_category' => [
                            'The major category does not match the division.'
                        ]
                    ]
                ],
                422
            );
        }

        //minor Category check
        $majorCategory = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->where('division_id', $request->division_id)->first()->id;
        $minorCategoryCheck = MinorCategory::withTrashed()->where('id', $request->minor_category_id)
            ->where('major_category_id',$majorCategory)->exists();
        if ($request->status != 'Disposed') {
            //check minor catrgory if softDelete
            if ( MinorCategory::onlyTrashed()->where('id', $request->minor_category_id)
                ->where('major_category_id',$majorCategory)->exists()) {
                return response()->json(
                    [
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'minor_category' => [
                                'Conflict with minor category and fixed asset status.'
                            ]
                        ]
                    ],
                    422
                );
            }
        }
        if(!$minorCategoryCheck) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'minor_category' => [
                            'The minor category does not match the major category.'
                        ]
                    ]
                ],
                422
            );
        }
        //if no changes in all fields
//        if( FixedAsset::where('id', $id)->first()) {
//            return response()->json([
//                'message' => 'No changes made.',
//                'data' => $request->all()
//            ], 200);
//        }

        $fixedAsset = FixedAsset::where('id', $id)->where('status','!=','Disposed' )->first();
        if ($fixedAsset) {
            $fixedAsset->update([
                'capex' => $request->capex ?? '-',
                'project_name' => $request->project_name ?? '-',
//                'vladimir_tag_number' => $request->vladimir_tag_number,
                'tag_number' => $request->tag_number ?? '-',
                'tag_number_old' => $request->tag_number_old ?? '-',
                'asset_description' => $request->asset_description,
                'type_of_request' => $request->type_of_request,
                'asset_specification' => $request->asset_specification,
                'accountability' => $request->accountability,
                'accountable' => $request->accountable,
                'cellphone_number' => $request->cellphone_number ?? '-',
                'brand' => $request->brand ?? '-',
                'division_id' => $request->division_id,
                'major_category_id' => $request->major_category_id,
                'minor_category_id' => $request->minor_category_id,
                'voucher' => $request->voucher ?? '-',
                'receipt' => $request->receipt ?? '-',
                'quantity' => $request->quantity,
                'depreciation_method' => $request->depreciation_method,
                'est_useful_life' => $request->est_useful_life,
                'acquisition_date' => $request->acquisition_date,
                'acquisition_cost' => $request->acquisition_cost,
                'status' => $request->status ?? $fixedAsset->status,
                'is_old_asset' => $request->is_old_asset ?? 0,
                'care_of' => $request->care_of,
                'company_id' => $request->company_id,
                'company_name' => Company::where('id', $request->company_id)->value('company_name'),
                'department_id' => $request->department_id,
                'department_name' => Department::where('id', $request->department_id)->value('department_name'),
                'location_id' => $request->location_id,
                'location_name' => Location::where('id', $request->location_id)->value('location_name'),
                'account_id' => $request->account_title_id,
                'account_title' => AccountTitle::where('id', $request->account_title_id)->value('account_title_name'),
            ]);

            $fixedAsset->formula()->update([
                'depreciation_method' => $request->depreciation_method,
                'est_useful_life' => $request->est_useful_life,
                'acquisition_date' => $request->acquisition_date,
                'acquisition_cost' => $request->acquisition_cost,
                'scrap_value' => $request->scrap_value,
                'original_cost' => $request->original_cost,
                'accumulated_cost' => $request->accumulated_cost,
                'age' => $request->age,
                'end_depreciation' => $request->end_depreciation,
                'depreciation_per_year' => $request->depreciation_per_year,
                'depreciation_per_month' => $request->depreciation_per_month,
                'remaining_book_value' => $request->remaining_book_value,
                'start_depreciation' => $request->start_depreciation,
            ]);

            return response()->json([
                'message' => 'Fixed Asset updated successfully',
                'data' => $fixedAsset->load('formula')
            ], 200);
        }else{
            return response()->json([
                'message' => 'Fixed Asset Route Not Found.'
            ], 404);
        }

//        if(FixedAsset::where('id', $id)->where('is_active', true)->exists()){
//            $fixedAsset = FixedAsset::where('id', $id)->update([
//                'capex' => $request->capex ?? '-',
//                'project_name' => $request->project_name,
//                'vladimir_tag_number' => (new MasterlistImport())->vladimirTagGenerator(),
//                'tag_number' => $request->tag_number,
//                'tag_number_old' => $request->tag_number_old,
//                'asset_description' => $request->asset_description,
//                'type_of_request' => $request->type_of_request,
//                'asset_specification' => $request->asset_specification,
//                'accountability' => $request->accountability,
//                'accountable' => $request->accountable,
//                'cellphone_number' => $request->cellphone_number ?? '-',
//                'brand' => $request->brand ?? '-',
//                'division_id' => Division::where('division_name', $request->division)->first()->id,
//                'major_category_id' => MajorCategory::where('major_category_name', $request->major_category)->first()->id,
//                'minor_category_id' => MinorCategory::where('minor_category_name', $request->minor_category)->first()->id,
//                'voucher' => $request->voucher ?? '-',
//                'receipt' => $request->receipt ?? '-',
//                'quantity' => $request->quantity,
//                'depreciation_method' => $request->depreciation_method,
//                'est_useful_life' => $request->est_useful_life,
//                'acquisition_date' => $request->acquisition_date, //TODO:
//                'acquisition_cost' => $request->acquisition_cost,
//                'is_active' => $request->status ?? 1,
//                'care_of' => $request->care_of,
//                'company_id' => Company::where('company_code', $request->company_code)->first()->id,
//                'company_name' => $request->company,
//                'department_id' => Department::where('department_code', $request->department_code)->first()->id,
//                'department_name' => $request->department,
//                'location_id' => Location::where('location_code', $request->location_code)->first()->id,
//                'location_name' => $request->location,
//                'account_id' => AccountTitle::where('account_title_code', $request->account_code)->first()->id,
//                'account_title' => $request->account_title,
//            ]);
//
//            $formula = Formula::where('fixed_asset_id', $id)->update([
//                'depreciation_method' => $request->depreciation_method,
//                'est_useful_life' => $request->est_useful_life,
//                'acquisition_date' => $request->acquisition_date,
//                'acquisition_cost' => $request->acquisition_cost,
//                'scrap_value' => $request->scrap_value,
//                'original_cost' => $request->original_cost,
//                'accumulated_cost' => $request->accumulated_cost,
//                'age' => $request->age,
//                'end_depreciation' => $request->end_depreciation,
//                'depreciation_per_year' => $request->depreciation_per_year,
//                'depreciation_per_month' => $request->depreciation_per_month,
//                'remaining_book_value' => $request->remaining_book_value,
//                'start_depreciation' => $request->start_depreciation,
//            ]);
//
//
//
//            return response()->json([
//                'message' => 'Fixed Asset updated successfully.',
//                'data' => $fixedAsset = FixedAsset::where('id', $id)->with('formula')->first()
//            ], 200);
//        }else{
//            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
//        }
    }

    public function archived(FixedAssetRequest $request, $id)
    {

        //TODO: Check for possible way to change status to other than "Disposed"
        //uppercase first letter of status
        $status = $request->status;
        $fixedAsset = FixedAsset::query();
        $formula = Formula::query();
        if (!$fixedAsset->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
        }

        if ($status == "Disposed") {
            if (!FixedAsset::where('id', $id)->where('status', "Disposed")->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $fixedAsset->where('id', $id)->update(['status' => "Disposed"]);
                $fixedAsset->where('id', $id)->delete();
                $formula->where('fixed_asset_id', $id)->delete();
                return response()->json(['message' => 'Successfully Disposed!'], 200);
            }
        }
        if ($status == "Good" || $status == "For Repair" || $status == "For Disposal" || $status == "Spare" || $status == "Sold"|| $status == "Write Off") {
            if (FixedAsset::where('id', $id)->where('status', $status)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $checkMinorCategory = MinorCategory::where('id', $fixedAsset->where('id', $id)->first()->minor_category_id)->exists();
                if(!$checkMinorCategory){
                    return response()->json(['error' => 'Unable to Restore!, Minor Category was Archived!'], 404);
                }
                $fixedAsset->withTrashed()->where('id', $id)->restore();
                $fixedAsset->update(['status' => $status]);
                $formula->where('fixed_asset_id', $id)->restore();
                return response()->json(['message' => 'Successfully Change status'], 200);
            }
        }
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
                    ->orWhere('accountable', 'LIKE', '%' . $search . '%')
                    ->orWhere('brand', 'LIKE', '%' . $search . '%')
                    ->orWhere('depreciation_method', 'LIKE', '%' . $search . '%');
                $query->orWhereHas('majorCategory', function ($query) use ($search) {
                    $query->withTrashed()->where('major_category_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('minorCategory', function ($query) use ($search) {
                    $query->withTrashed()->where('minor_category_name', 'LIKE', '%' . $search . '%');
                });
                $query->orWhereHas('division', function ($query) use ($search) {
                    $query->withTrashed()->where('division_name', 'LIKE', '%' . $search . '%');
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
                'asset_description' => $item->asset_description,
                'type_of_request' => $item->type_of_request,
                'asset_specification' => $item->asset_specification,
                'accountability' => $item->accountability,
                'accountable' => $item->accountable,
                'cellphone_number' => $item->cellphone_number,
                'brand' => $item->brand,
                'division' => [
                    'id' => $item->division->id,
                    'division_name' => $item->division->division_name,
                ],
                'major_category' => [
                    'id' => $item->majorCategory->id,
                    'major_category_name' => $item->majorCategory->major_category_name,
                ],
                'minor_category' => [
                    'id' => $item->minorCategory->id,
                    'minor_category_name' => $item->minorCategory->minor_category_name,
                ],
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
                'is_old_asset' => $item->is_old_asset,
                'care_of' => $item->care_of,
                'age' => $item->formula->age,
                'end_depreciation' => $item->formula->end_depreciation,
                'depreciation_per_year' => $item->formula->depreciation_per_year,
                'depreciation_per_month' => $item->formula->depreciation_per_month,
                'remaining_book_value' => $item->formula->remaining_book_value,
                'start_depreciation' => $item->formula->start_depreciation,
                'company' => [
                    'id' => $item->company->id,
                    'company_code' => $item->company->company_code,
                    'company_name' => $item->company->company_name,
                ],
                'department' => [
                    'id' => $item->department->id,
                    'department_code' => $item->department->department_code,
                    'department_name' => $item->department->department_name,
                ],
                'location' => [
                    'id' => $item->location->id,
                    'location_code' => $item->location->location_code,
                    'location_name' => $item->location->location_name,
                ],
                'account_title' => [
                    'id' => $item->accountTitle->id,
                    'account_title_code' => $item->accountTitle->account_title_code,
                    'account_title_name' => $item->accountTitle->account_title_name,
                ],
            ];
        });
        return $fixedAsset;
    }

    public function searchAssetTag(Request $request){
        $search = $request->get('search');
        if($search == null){
            return response()->json([
                'message' => 'No data found'
            ], 404);
        }
        $fixedAsset = FixedAsset::withTrashed()
            ->with('division',function ($query){
                $query->withTrashed();
            })
            ->with('majorCategory',function ($query){
                $query->withTrashed();
            })
            ->with('minorCategory',function ($query){
                $query->withTrashed();
            })
            ->with('formula',function ($query){
                $query->withTrashed();
            })
            ->where('vladimir_tag_number', $search)->first();

        $fixed_asset_arr = [
            'id' => $fixedAsset->id,
            'capex' => $fixedAsset->capex,
            'project_name' => $fixedAsset->project_name,
            'vladimir_tag_number' => $fixedAsset->vladimir_tag_number,
            'tag_number' => $fixedAsset->tag_number,
            'tag_number_old' => $fixedAsset->tag_number_old,
            'asset_description' => $fixedAsset->asset_description,
            'type_of_request' => $fixedAsset->type_of_request,
            'asset_specification' => $fixedAsset->asset_specification,
            'accountability' => $fixedAsset->accountability,
            'accountable' => $fixedAsset->accountable,
            'cellphone_number' => $fixedAsset->cellphone_number,
            'brand' => $fixedAsset->brand,
            'division' => [
                'id' => $fixedAsset->division->id,
                'division_name' => $fixedAsset->division->division_name,
            ],
            'major_category' => [
                'id' => $fixedAsset->majorCategory->id,
                'major_category_name' => $fixedAsset->majorCategory->major_category_name,
            ],
            'minor_category' => [
                'id' => $fixedAsset->minorCategory->id,
                'minor_category_name' => $fixedAsset->minorCategory->minor_category_name,
            ],
            'voucher' => $fixedAsset->voucher,
            'receipt' => $fixedAsset->receipt,
            'quantity' => $fixedAsset->quantity,
            'depreciation_method' => $fixedAsset->depreciation_method,
            'est_useful_life' => $fixedAsset->est_useful_life,
            //                    'salvage_value' => $fixedAsset->salvage_value,
            'acquisition_date' => $fixedAsset->acquisition_date,
            'acquisition_cost' => $fixedAsset->acquisition_cost,
            'scrap_value' => $fixedAsset->formula->scrap_value,
            'original_cost' => $fixedAsset->formula->original_cost,
            'accumulated_cost' => $fixedAsset->formula->accumulated_cost,
            'status' => $fixedAsset->is_active,
            'is_old_asset' => $fixedAsset->is_old_asset,
            'care_of' => $fixedAsset->care_of,
            'age' => $fixedAsset->formula->age,
            'end_depreciation' => $fixedAsset->formula->end_depreciation,
            'depreciation_per_year' => $fixedAsset->formula->depreciation_per_year,
            'depreciation_per_month' => $fixedAsset->formula->depreciation_per_month,
            'remaining_book_value' => $fixedAsset->formula->remaining_book_value,
            'start_depreciation' => $fixedAsset->formula->start_depreciation,
            'company' => [
                'id' => $fixedAsset->company->id,
                'company_code' => $fixedAsset->company->company_code,
                'company_name' => $fixedAsset->company->company_name,
            ],
            'department' => [
                'id' => $fixedAsset->department->id,
                'department_code' => $fixedAsset->department->department_code,
                'department_name' => $fixedAsset->department->department_name,
            ],
            'location' => [
                'id' => $fixedAsset->location->id,
                'location_code' => $fixedAsset->location->location_code,
                'location_name' => $fixedAsset->location->location_name,
            ],
            'account_title' => [
                'id' => $fixedAsset->accountTitle->id,
                'account_title_code' => $fixedAsset->accountTitle->account_title_code,
                'account_title_name' => $fixedAsset->accountTitle->account_title_name,
            ],
        ];

        return response()->json([
            'message' => 'Fixed Asset retrieved successfully.',
            'data' => $fixedAsset
        ], 200);


    }
}
