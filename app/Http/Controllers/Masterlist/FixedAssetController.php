<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedAsset\FixedAssetRequest;
use App\Http\Requests\FixedAsset\FixedAssetUpdateRequest;
use App\Imports\MasterlistImport;
use App\Models\AccountTitle;
use App\Models\Capex;
use App\Models\Company;
use App\Models\Department;
use App\Models\Division;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\SubCapex;
use App\Models\TypeOfRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FixedAssetController extends Controller
{
    public function index()
    {
        $fixed_assets = FixedAsset::with('formula')->get();
        return response()->json([
            'message' => 'Fixed Assets retrieved successfully.',
            'data' => $fixed_assets
        ], 200);
    }


    public function store(FixedAssetRequest $request)
    {

        $vladimirTagNumber = (new MasterlistImport())->vladimirTagGenerator();
        if (!is_numeric($vladimirTagNumber) || strlen($vladimirTagNumber) != 13) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => 'Wrong vladimir tag number format. Please try again.'
            ], 422);
        }

        //minor Category check
        $majorCategory = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->first()->id;
        $minorCategoryCheck = MinorCategory::withTrashed()->where('id', $request->minor_category_id)
            ->where('major_category_id', $majorCategory)->exists();
//        if ($request->faStatus != 'Disposed') {
//            //check minor catrgory if softDelete
//            if (MinorCategory::onlyTrashed()->where('id', $request->minor_category_id)
//                ->where('major_category_id', $majorCategory)->exists()) {
//                return response()->json(
//                    [
//                        'message' => 'The given data was invalid.',
//                        'errors' => [
//                            'minor_category' => [
//                                'Conflict with minor category and fixed asset status.'
//                            ]
//                        ]
//                    ],
//                    422
//                );
//            }
//        }
        if (!$minorCategoryCheck) {
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
        $departmentQuery = Department::where('id', $request->department_id)->first();
        if ($departmentQuery->division_id == null) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'division' => [
                            'The division is required.'
                        ]
                    ]
                ],
                422
            );
        }

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

//        if ($request->faStatus == 'Disposed') {
//            $fixedAsset->delete();
//            $fixedAsset->formula()->delete();
//            return response()->json([
//                'message' => 'Fixed Asset created successfully, but disposed immediately.',
//                'data' => $fixedAsset->withTrashed()->with([
//                        'formula' => function ($query) {
//                            $query->withTrashed();
//                        }]
//                )->where('id', $fixedAsset->id)->first()
//            ], 201);
//        }

        //return the fixed asset and formula
        return response()->json([
            'message' => 'Fixed Asset created successfully.',
            'data' => $fixedAsset->with('formula')->where('id', $fixedAsset->id)->first()
        ], 201);
    }

    public function show(int $id)
    {
        $fixed_asset = FixedAsset::withTrashed()->with('formula', function ($query) {
            $query->withTrashed();
        })
            ->where('id', $id)->first();
        //        return $fixed_asset->majorCategory->major_category_name;
        if (!$fixed_asset) {
            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
        }
//        $fixed_asset->with('formula', function ($query){
//            $query->withTrashed();
//        })->where('id', $id)->first();

        $fixed_asset_arr = [
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
                'id' => $fixed_asset->division->id ?? '-',
                'division_name' => $fixed_asset->division->division_name ?? '-',
            ],
            'major_category' => [
                'id' => $fixed_asset->majorCategory->id ?? '-',
                'major_category_name' => $fixed_asset->majorCategory->major_category_name ?? '-',
                'est_useful_life' => $fixed_asset->majorCategory->est_useful_life ?? '-',
            ],
            'minor_category' => [
                'id' => $fixed_asset->minorCategory->id ?? '-',
                'minor_category_name' => $fixed_asset->minorCategory->minor_category_name ?? '-',
            ],
            'voucher' => $fixed_asset->voucher,
            'receipt' => $fixed_asset->receipt,
            'quantity' => $fixed_asset->quantity,
            'depreciation_method' => $fixed_asset->depreciation_method,
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date,
            'acquisition_cost' => $fixed_asset->acquisition_cost,
            'scrap_value' => $fixed_asset->formula->scrap_value,
            'original_cost' => $fixed_asset->formula->original_cost,
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
            'age' => $fixed_asset->formula->age,
            'end_depreciation' => $fixed_asset->formula->end_depreciation,
            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
            'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
            'release_date' => $fixed_asset->formula->release_date,
            'start_depreciation' => $fixed_asset->formula->start_depreciation,
            'company' => [
                'id' => $fixed_asset->company->id ?? '-',
                'company_code' => $fixed_asset->company->company_code ?? '-',
                'company_name' => $fixed_asset->company->company_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->location->id ?? '-',
                'location_code' => $fixed_asset->location->location_code ?? '-',
                'location_name' => $fixed_asset->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $fixed_asset->accountTitle->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->account_title_name ?? '-',
            ],
        ];
        return response()->json([
            'message' => 'Fixed Asset retrieved successfully.',
            'data' => $fixed_asset_arr
        ], 200);
    }


    //TODO: Ask on what should and should not be updated on the fixed asset
    public function update(FixedAssetUpdateRequest $request, int $id)
    {
        $request->validated();
        //minor Category check
        $majorCategory = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->first()->id;
        $minorCategoryCheck = MinorCategory::withTrashed()->where('id', $request->minor_category_id)
            ->where('major_category_id', $majorCategory)->exists();
//        if ($request->faStatus != 'Disposed') {
//            //check minor catrgory if softDelete
//            if (MinorCategory::onlyTrashed()->where('id', $request->minor_category_id)
//                ->where('major_category_id', $majorCategory)->exists()) {
//                return response()->json(
//                    [
//                        'message' => 'The given data was invalid.',
//                        'errors' => [
//                            'minor_category' => [
//                                'Conflict with minor category and fixed asset status.'
//                            ]
//                        ]
//                    ],
//                    422
//                );
//            }
//        }
        if (!$minorCategoryCheck) {
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
        $departmentQuery = Department::where('id', $request->department_id)->first();
        if ($departmentQuery->division_id == null) {
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'division' => [
                            'The division is required.'
                        ]
                    ]
                ],
                422
            );
        }

        $fixedAsset = FixedAsset::where('id', $id)->first();
        if ($fixedAsset) {
            $fixedAsset->update([
                'capex_id' => SubCapex::where('id', $request->sub_capex_id)->first()->capex_id ?? null,
                'sub_capex_id' => $request->sub_capex_id ?? null,
                'tag_number' => $request->tag_number ?? '-',
                'tag_number_old' => $request->tag_number_old ?? '-',
                'asset_description' => $request->asset_description,
                'type_of_request_id' => $request->type_of_request_id,
                'asset_specification' => $request->asset_specification,
                'accountability' => ucwords(strtolower($request->accountability)),
                'accountable' => $request->accountable,
                'cellphone_number' => $request->cellphone_number ?? '-',
                'brand' => $request->brand ?? '-',
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
                'care_of' => $request->care_of ?? '-',
                'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
                'department_id' => $departmentQuery->id ?? null,
                'location_id' => Location::where('sync_id', $departmentQuery->location_sync_id)->first()->id ?? null,
                'account_id' => $request->account_title_id,
            ]);

            $fixedAsset->formula()->update([
                'depreciation_method' => $request->depreciation_method,
                'acquisition_date' => $request->acquisition_date,
                'acquisition_cost' => $request->acquisition_cost,
                'scrap_value' => $request->scrap_value,
                'original_cost' => $request->original_cost,
                'accumulated_cost' => $request->accumulated_cost ?? 0,
                'age' => $request->age,
                'end_depreciation' => $this->getEndDepreciation($request->start_depreciation, $request->est_useful_life),
                'depreciation_per_year' => $request->depreciation_per_year ?? 0,
                'depreciation_per_month' => $request->depreciation_per_month ?? 0,
                'remaining_book_value' => $request->remaining_book_value ?? 0,
                'release_date' => $request->release_date,
                'start_depreciation' => $this->getStartDepreciation($request->release_date),
            ]);

            return response()->json([
                'message' => 'Fixed Asset updated successfully',
                'data' => $fixedAsset->load('formula'),
            ], 200);
        } else {
            return response()->json([
                'message' => 'Fixed Asset Route Not Found.'
            ], 404);
        }
    }

//    public function archived(Request $request, $id)
//    {
//        $request->validate([
//            'faStatus' => 'required|in:Good,For Disposal,Disposed,For Repair,Spare,Sold,Write Off',
//        ],
//            [
//                'faStatus.required' => 'Status is required.',
//                'faStatus.in' => 'Status must be Good, For Disposal, Disposed, For Repair, Spare, Sold, Write Off.',
//            ]);
//
//        $faStatus = $request->faStatus;
//        $faStatus = ucwords($faStatus);
//        $fixedAsset = FixedAsset::query();
//        $formula = Formula::query();
//        if (!$fixedAsset->withTrashed()->where('id', $id)->exists()) {
//            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
//        }
//
//        if ($faStatus === "Disposed") {
//            if (!FixedAsset::where('id', $id)->WhereIn('faStatus', ['Good', 'For Disposal', 'For Repair', 'Spare', 'Sold', 'Write Off'])->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
//            } else {
//                $fixedAsset->where('id', $id)->update(['faStatus' => "Disposed"]);
//                $fixedAsset->where('id', $id)->delete();
//                $formula->where('fixed_asset_id', $id)->delete();
//                return response()->json(['message' => 'Successfully Disposed!'], 200);
//            }
//        }
//        if ($faStatus === "Good" || $faStatus === "For Repair" || $faStatus === "For Disposal" || $faStatus === "Spare" || $faStatus === "Sold" || $faStatus === "Write Off") {
//            if (FixedAsset::where('id', $id)->where('faStatus', $faStatus)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
//            } else {
//                $checkMinorCategory = MinorCategory::where('id', $fixedAsset->where('id', $id)->first()->minor_category_id)->exists();
//                if (!$checkMinorCategory) {
//                    return response()->json(['error' => 'Unable to Restore!, Minor Category was Archived!'], 404);
//                }
//                //division
//                $checkDivision = Division::where('id', $fixedAsset->where('id', $id)->first()->division_id)->exists();
//                if (!$checkDivision) {
//                    return response()->json(['error' => 'Unable to Restore!, Division was Archived!'], 404);
//                }
//                //capex
//                $checkCapex = Capex::where('id', $fixedAsset->where('id', $id)->first()->capex_id)->exists();
//                if (!$checkCapex) {
//                    return response()->json(['error' => 'Unable to Restore!, Capex was Archived!'], 404);
//                }
//                //typeofrequest
//                $checkTypeOfRequest = TypeOfRequest::where('id', $fixedAsset->where('id', $id)->first()->type_of_request_id)->exists();
//                if (!$checkTypeOfRequest) {
//                    return response()->json(['error' => 'Unable to Restore!, Type of Request was Archived!'], 404);
//                }
//
//                $fixedAsset->withTrashed()->where('id', $id)->restore();
//                $fixedAsset->update(['faStatus' => $faStatus]);
//                $formula->where('fixed_asset_id', $id)->restore();
//                return response()->json(['message' => 'Successfully Change status'], 200);
//            }
//        }
//    }

    public function search(Request $request)
    {
        $search = $request->get('search');
        $limit = $request->get('limit');
        $page = $request->get('page');
//        $faStatus = $request->get('faStatus');

//        if ($faStatus === null) {
//            $faStatus = ['Good', 'For Disposal', 'For Repair', 'Spare', 'Sold', 'Write Off', 'Disposed'];
//        } elseif ($faStatus == 'Disposed, Sold' || $faStatus == 'Disposed' || $faStatus == 'Sold') {
//            if($faStatus == 'Disposed, Sold'){
//                $faStatus = ['Disposed', 'Sold'];
//            }elseif($faStatus == 'Sold'){
//                $faStatus = ['Sold'];
//            }elseif($faStatus == 'Disposed'){
//                $faStatus = ['Disposed'];
//            }
//        } else {
//            $faStatus = array_filter(array_map('trim', explode(',', $faStatus)), function ($status) {
//                return $status !== 'Disposed';
//            });
//        }

        $fixedAsset = FixedAsset::withTrashed()
//            ->with([
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
            return [
                'id' => $item->id,
                'capex' => [
                    'id' => $item->capex->id ?? '-',
                    'capex' => $item->capex->capex ?? '-',
                    'project_name' => $item->capex->project_name ?? '-',
                ],
                'sub_capex' => [
                    'id' => $item->subCapex->id ?? '-',
                    'sub_capex' => $item->subCapex->sub_capex ?? '-',
                    'sub_project' => $item->subCapex->sub_project ?? '-',
                ],
                'vladimir_tag_number' => $item->vladimir_tag_number,
                'tag_number' => $item->tag_number,
                'tag_number_old' => $item->tag_number_old,
                'asset_description' => $item->asset_description,
                'type_of_request' => [
                    'id' => $item->typeOfRequest->id ?? '-',
                    'type_of_request_name' => $item->typeOfRequest->type_of_request_name ?? '-',
                ],
                'asset_specification' => $item->asset_specification,
                'accountability' => $item->accountability,
                'accountable' => $item->accountable,
                'cellphone_number' => $item->cellphone_number,
                'brand' => $item->brand,
                'division' => [
                    'id' => $item->division->id ?? '-',
                    'division_name' => $item->division->division_name ?? '-',
                ],
                'major_category' => [
                    'id' => $item->majorCategory->id ?? '-',
                    'major_category_name' => $item->majorCategory->major_category_name ?? '-',
                    'est_useful_life' => $item->majorCategory->est_useful_life ?? '-',
                ],
                'minor_category' => [
                    'id' => $item->minorCategory->id ?? '-',
                    'minor_category_name' => $item->minorCategory->minor_category_name ?? '-',
                ],
                'voucher' => $item->voucher,
                'receipt' => $item->receipt,
                'quantity' => $item->quantity,
                'depreciation_method' => $item->depreciation_method,
                //                    'salvage_value' => $item->salvage_value,
                'acquisition_date' => $item->acquisition_date,
                'acquisition_cost' => $item->acquisition_cost,
                'scrap_value' => $item->formula->scrap_value,
                'original_cost' => $item->formula->original_cost,
                'accumulated_cost' => $item->formula->accumulated_cost,
                'asset_status' => [
                    'id' => $item->assetStatus->id ?? '-',
                    'asset_status_name' => $item->assetStatus->asset_status_name ?? '-',
                ],
                'cycle_count_status' => [
                    'id' => $item->cycleCountStatus->id ?? '-',
                    'cycle_count_status_name' => $item->cycleCountStatus->cycle_count_status_name ?? '-',
                ],
                'depreciation_status' => [
                    'id' => $item->depreciationStatus->id ?? '-',
                    'depreciation_status_name' => $item->depreciationStatus->depreciation_status_name ?? '-',
                ],
                'movement_status' => [
                    'id' => $item->movementStatus->id ?? '-',
                    'movement_status_name' => $item->movementStatus->movement_status_name ?? '-',
                ],
                'is_old_asset' => $item->is_old_asset,
                'care_of' => $item->care_of,
                'age' => $item->formula->age,
                'end_depreciation' => $item->formula->end_depreciation,
                'depreciation_per_year' => $item->formula->depreciation_per_year,
                'depreciation_per_month' => $item->formula->depreciation_per_month,
                'remaining_book_value' => $item->formula->remaining_book_value,
                'release_date' => $item->formula->release_date,
                'start_depreciation' => $item->formula->start_depreciation,
                'company' => [
                    'id' => $item->company->id ?? '-',
                    'company_code' => $item->company->company_code ?? '-',
                    'company_name' => $item->company->company_name ?? '-',
                ],
                'department' => [
                    'id' => $item->department->id ?? '-',
                    'department_code' => $item->department->department_code ?? '-',
                    'department_name' => $item->department->department_name ?? '-',
                ],
                'location' => [
                    'id' => $item->location->id ?? '-',
                    'location_code' => $item->location->location_code ?? '-',
                    'location_name' => $item->location->location_name ?? '-',
                ],
                'account_title' => [
                    'id' => $item->accountTitle->id ?? '-',
                    'account_title_code' => $item->accountTitle->account_title_code ?? '-',
                    'account_title_name' => $item->accountTitle->account_title_name ?? '-',
                ],
            ];
        });
        return $fixedAsset;
    }

    function assetDepreciation(Request $request, $id)
    {
        //validation
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m',
        ],
            [
                'date.required' => 'Date is required.',
                'date.date_format' => 'Date format is invalid.',
            ]);

        $fixedAsset = FixedAsset::with('formula')->where('id', $id)->first();
        if (!$fixedAsset) {
            return response()->json([
                'message' => 'Route not found.'
            ], 404);
        }

        //Variable declaration
        $depreciation_method = $fixedAsset->depreciation_method;
        $est_useful_life = $fixedAsset->est_useful_life;
        $start_depreciation = $fixedAsset->formula->start_depreciation;
        $original_cost = $fixedAsset->formula->original_cost;
        $scrap_value = $fixedAsset->formula->scrap_value;
        $end_depreciation = $fixedAsset->formula->end_depreciation;
        $release_date = $fixedAsset->formula->release_date;
        $custom_end_depreciation = $validator->validated()['date'];

        if ($custom_end_depreciation == date('Y-m')) {
            if ($custom_end_depreciation > $end_depreciation) {
                $custom_end_depreciation = $end_depreciation;
            }
        } elseif (!$this->dateValidation($custom_end_depreciation, $start_depreciation, $fixedAsset->formula->end_depreciation)) {
            return response()->json([
                'message' => 'Date is invalid.'
            ], 422);
        }

        //calculation variables
        $custom_age = $this->getAge($start_depreciation, $custom_end_depreciation);
        $monthly_depreciation = $this->getMonthlyDepreciation($original_cost, $est_useful_life);
        $yearly_depreciation = $this->getYearlyDepreciation($original_cost, $est_useful_life);
        $accumulated_cost = $this->getAccumulatedCost($monthly_depreciation, $custom_age);
        $remaining_book_value = $this->getRemainingBookValue($original_cost, $accumulated_cost);


        if ($fixedAsset->depreciation_method == 'One Time') {
            $age = 0.083333333333333;
            $monthly_depreciation = $this->getMonthlyDepreciation($original_cost, $age);
            return response()->json([
                'message' => 'Depreciation retrieved successfully.',
                'data' => [
                    'depreciation_method' => $depreciation_method,
                    'original_cost' => $original_cost,
                    'start_depreciation' => $start_depreciation,
                    'end_depreciation' => $end_depreciation,
                    'depreciation' => $monthly_depreciation,
                    'depreciation_per_month' => 0,
                    'depreciation_per_year' => 0,
                    'accumulated_cost' => 0,
                    'remaining_book_value' => 0,

                ]
            ], 200);
        }
        return response()->json([
            'message' => 'Depreciation calculated successfully',
            'data' => [
                'depreciation_method' => $depreciation_method,
                'original_cost' => $original_cost,
                'est_useful_life' => $est_useful_life,
                'age' => $custom_age,
                'scarp_value' => $scrap_value,
                'start_depreciation' => $start_depreciation,
                'end_depreciation' => $end_depreciation,
                'depreciation_per_month' => $monthly_depreciation,
                'depreciation_per_year' => $yearly_depreciation,
                'accumulated_cost' => $accumulated_cost,
                'remaining_book_value' => $remaining_book_value,
            ]
        ], 200);

    }

    private function getAge($start_depreciation, $custom_end_depreciation)
    {
        $start_depreciation = Carbon::parse($start_depreciation);
        $custom_end_depreciation = Carbon::parse($custom_end_depreciation);
        return $start_depreciation->diffInMonths($custom_end_depreciation->addMonth(1));
    }

    private function getYearlyDepreciation($original_cost, $est_useful_life): float
    {
        return round($original_cost / $est_useful_life, 2);
    }

    private function getMonthlyDepreciation($original_cost, $est_useful_life): float
    {
        $yearly = $original_cost / $est_useful_life;
        return round($yearly / 12, 2);
    }

    private function dateValidation($date, $start_depreciation, $end_depreciation): bool
    {
        $date = Carbon::parse($date);
        $start_depreciation = Carbon::parse($start_depreciation);
        $end_depreciation = Carbon::parse($end_depreciation);
        if ($date->between($start_depreciation, $end_depreciation)) {
            return true;
        } else {
            return false;
        }
    }

    private function getAccumulatedCost($monthly_depreciation, float $custom_age): float
    {
        $accumulated_cost = $monthly_depreciation * $custom_age;
        return round($accumulated_cost);
    }

    private function getRemainingBookValue($original_cost, float $accumulated_cost): float
    {
        $remaining_book_value = $original_cost - $accumulated_cost;
        return round($remaining_book_value);
    }

    public function getEndDepreciation($start_depreciation, $est_useful_life)
    {
        $start_depreciation = Carbon::parse($start_depreciation);
        return $start_depreciation->addYears(floor($est_useful_life))->addMonths(floor(($est_useful_life - floor($est_useful_life)) * 12))->subMonth(1)->format('Y-m');
    }

    private function getStartDepreciation($release_date)
    {
        $release_date = Carbon::parse($release_date);
        return $release_date->addMonth(1)->format('Y-m');
    }

    public function showTagNumber(int $tagNumber)
    {
        $fixed_asset = FixedAsset::withTrashed()->with('formula', function ($query) {
            $query->withTrashed();
        })->where('vladimir_tag_number', $tagNumber)->first();

        if (!$fixed_asset) {
            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
        }

        $fixed_asset_arr = [
            'id' => $fixed_asset->id,
            'capex' => $fixed_asset->subCapex->sub_capex ?? "-",
            'project_name' => $fixed_asset->project_name,
            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
            'tag_number' => $fixed_asset->tag_number,
            'tag_number_old' => $fixed_asset->tag_number_old,
            'asset_description' => $fixed_asset->asset_description,
            'type_of_request' => [
                'id' => $fixed_asset->typeOfRequest->id,
                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name,
            ],
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
            'faStatus' => $fixed_asset->faStatus,
            'is_old_asset' => $fixed_asset->is_old_asset,
            'care_of' => $fixed_asset->care_of,
            'age' => $fixed_asset->formula->age,
            'end_depreciation' => $fixed_asset->formula->end_depreciation,
            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
            'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
            'release_date' => $fixed_asset->formula->release_date,
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
}
