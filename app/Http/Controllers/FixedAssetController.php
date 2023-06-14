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
        if ($request->depreciation_method !== 'STL') { //todo: add other depreciation methods
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'depreciation_method' => [
                            'Only Straight Line Method is allowed for now.'
                        ]
                    ]

                ],
                422
            );
        }
        $vladimirTagNumber = (new MasterlistImport())->vladimirTagGenerator();
        if (!is_numeric($vladimirTagNumber) || strlen($vladimirTagNumber) != 13) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['Wrong vladimir tag number format. Please try again.']
            ], 422);
        }
        //Major Category check
        $majorCategoryCheck = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->where('division_id', $request->division_id)->exists();
        if (!$majorCategoryCheck) {
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
            ->where('major_category_id', $majorCategory)->exists();
        if ($request->fa_status != 'Disposed') {
            //check minor catrgory if softDelete
            if (MinorCategory::onlyTrashed()->where('id', $request->minor_category_id)
                ->where('major_category_id', $majorCategory)->exists()) {
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

        $fixedAsset = FixedAsset::create([
            'capex' => $request->capex ?? '-',
            'project_name' => $request->project_name ?? '-',
            'vladimir_tag_number' => $vladimirTagNumber,
            'tag_number' => $request->tag_number ?? '-',
            'tag_number_old' => $request->tag_number_old ?? '-',
            'asset_description' => $request->asset_description,
            'type_of_request_id' => $request->type_of_request_id,
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
            'fa_status' => $request->fa_status,
            'is_old_asset' => $request->is_old_asset ?? 0,
            'care_of' => $request->care_of ?? '-',
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
//            $this->assetCalculations($request)
            'depreciation_method' => $request->depreciation_method,
            'est_useful_life' => $request->est_useful_life,
            'acquisition_date' => $request->acquisition_date,
            'acquisition_cost' => $request->acquisition_cost,
            'scrap_value' => $request->scrap_value,
            'original_cost' => $request->original_cost,
            'accumulated_cost' => $request->accumulated_cost ?? 0,
            'age' => $request->age,
            'end_depreciation' => $request->end_depreciation,
            'depreciation_per_year' => $request->depreciation_per_year ?? 0,
            'depreciation_per_month' => $request->depreciation_per_month ?? 0,
            'remaining_book_value' => $request->remaining_book_value ?? 0,
            'start_depreciation' => $request->start_depreciation
        ]);

        if ($request->fa_status == 'Disposed') {
            $fixedAsset->delete();
            $fixedAsset->formula()->delete();
            return response()->json([
                'message' => 'Fixed Asset created successfully, but disposed immediately.',
                'data' => $fixedAsset->withTrashed()->with([
                        'formula' => function ($query) {
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
            'type_of_request_id' => $fixed_asset->type_of_request_id,
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
            'fa_status' => $fixed_asset->fa_status,
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


    //TODO: Ask on what should and should not be updated on the fixed asset
    public function update(FixedAssetRequest $request, int $id)
    {
        $request->validated();
        if ($request->depreciation_method !== 'STL') { //todo: add other depreciation methods
            return response()->json(
                [
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'depreciation_method' => [
                            'Only Straight Line Method is allowed for now.'
                        ]
                    ]

                ],
                422
            );
        }

        //Major Category check
        $majorCategoryCheck = MajorCategory::withTrashed()->where('id', $request->major_category_id)
            ->where('division_id', $request->division_id)->exists();
        if (!$majorCategoryCheck) {
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
            ->where('major_category_id', $majorCategory)->exists();
        if ($request->fa_status != 'Disposed') {
            //check minor catrgory if softDelete
            if (MinorCategory::onlyTrashed()->where('id', $request->minor_category_id)
                ->where('major_category_id', $majorCategory)->exists()) {
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

        $fixedAsset = FixedAsset::where('id', $id)->where('fa_status', '!=', 'Disposed')->first();
        if ($fixedAsset) {
            $fixedAsset->update([
//                'capex' => $request->capex ?? '-',
//                'project_name' => $request->project_name ?? '-',
//                'vladimir_tag_number' => $request->vladimir_tag_number,
                'tag_number' => $request->tag_number ?? '-',
                'tag_number_old' => $request->tag_number_old ?? '-',
                'asset_description' => $request->asset_description,
                'type_of_request_id' => $request->type_of_request_id,
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
                'fa_status' => $request->fa_status ?? $fixedAsset->fa_status,
                'is_old_asset' => $request->is_old_asset ?? 0,
                'care_of' => $request->care_of ?? '-',
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
                'accumulated_cost' => $request->accumulated_cost ?? 0,
                'age' => $request->age,
                'end_depreciation' => $request->end_depreciation,
                'depreciation_per_year' => $request->depreciation_per_year ?? 0,
                'depreciation_per_month' => $request->depreciation_per_month ?? 0,
                'remaining_book_value' => $request->remaining_book_value ?? 0,
                'start_depreciation' => $request->start_depreciation,
            ]);

            return response()->json([
                'message' => 'Fixed Asset updated successfully',
                'data' => $fixedAsset->load('formula')
            ], 200);
        } else {
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
//                'type_of_request_id' => $request->type_of_request_id,
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

    public function archived(Request $request, $id)
    {
        $request->validate([
            'fa_status' => 'required|in:Good,For Disposal,Disposed,For Repair,Spare,Sold,Write Off',
        ],
            [
                'fa_status.required' => 'Status is required.',
                'fa_status.in' => 'Status must be Good, For Disposal, Disposed, For Repair, Spare, Sold, Write Off.',
            ]);

        //TODO: Check for possible way to change status to other than "Disposed"
        //uppercase first letter of status
        $fa_status = $request->fa_status;
        $fa_status = ucwords($fa_status);
        $fixedAsset = FixedAsset::query();
        $formula = Formula::query();
        if (!$fixedAsset->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'Fixed Asset Route Not Found'], 404);
        }

        if ($fa_status === "Disposed") {
            if (!FixedAsset::where('id', $id)->WhereIn('fa_status', ['Good', 'For Disposal', 'For Repair', 'Spare', 'Sold', 'Write Off'])->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $fixedAsset->where('id', $id)->update(['fa_status' => "Disposed"]);
                $fixedAsset->where('id', $id)->delete();
                $formula->where('fixed_asset_id', $id)->delete();
                return response()->json(['message' => 'Successfully Disposed!'], 200);
            }
        }
        if ($fa_status === "Good" || $fa_status === "For Repair" || $fa_status === "For Disposal" || $fa_status === "Spare" || $fa_status === "Sold" || $fa_status === "Write Off") {
            if (FixedAsset::where('id', $id)->where('fa_status', $fa_status)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $checkMinorCategory = MinorCategory::where('id', $fixedAsset->where('id', $id)->first()->minor_category_id)->exists();
                if (!$checkMinorCategory) {
                    return response()->json(['error' => 'Unable to Restore!, Minor Category was Archived!'], 404);
                }
                $fixedAsset->withTrashed()->where('id', $id)->restore();
                $fixedAsset->update(['fa_status' => $fa_status]);
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
        $fa_status = $request->get('fa_status');
        if ($fa_status == NULL) {
            //get all statuses other than Disposed
            $fa_status = array("Good", "For Repair", "For Disposal", "Spare", "Sold", "Write Off");
        }
        if ($fa_status == "Disposed") {
            $fa_status = "Disposed";
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
            ->where(function ($query) use ($fa_status) {
                //array of status or not array
                if (is_array($fa_status)) {
                    $query->whereIn('fa_status', $fa_status);
                } else {
                    $query->where('fa_status', $fa_status);
                }
            })
            ->where(function ($query) use ($search) {
                $query->where('capex', 'LIKE', '%' . $search . '%')
                    ->orWhere('project_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('vladimir_tag_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('tag_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('tag_number_old', 'LIKE', '%' . $search . '%')
                    ->orWhere('type_of_request_id', 'LIKE', '%' . $search . '%')
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
                'type_of_request' => [
                    'id' => $item->typeOfRequest->id,
                    'type_of_request_name' => $item->typeOfRequest->type_of_request_name,
                ],
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
                'fa_status' => $item->fa_status,
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

    public function searchAssetTag(Request $request)
    {
        $search = $request->get('search');
        if ($search == null) {
            return response()->json([
                'message' => 'No data found'
            ], 404);
        }
        $fixedAsset = FixedAsset::withTrashed()
            ->with('division', function ($query) {
                $query->withTrashed();
            })
            ->with('majorCategory', function ($query) {
                $query->withTrashed();
            })
            ->with('minorCategory', function ($query) {
                $query->withTrashed();
            })
            ->with('formula', function ($query) {
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
            'type_of_request' => [
                'id' => $fixedAsset->typeOfRequest->id,
                'type_of_request_name' => $fixedAsset->typeOfRequest->type_of_request_name,
            ],
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
            'fa_status' => $fixedAsset->fa_status,
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

    public function assetDepreciation(Request $request, $id)
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
        $depreciation_method = $fixedAsset->depreciation_method;
        $est_useful_life = $fixedAsset->est_useful_life;
        $start_depreciation = $fixedAsset->formula->start_depreciation;
        $acquisition_date = $fixedAsset->acquisition_date;
        $acquisition_cost = $fixedAsset->acquisition_cost;
        $scrap_value = $fixedAsset->formula->scrap_value;
        $original_cost = $fixedAsset->formula->original_cost;
        $custom_end_depreciation = $validator->validated()['date'];
        $age = $this->getAge($acquisition_date);

        //Calculations
        $custom_age = $this->getAge($acquisition_date, $custom_end_depreciation);
        $depreciation_per_year = $this->getDepreciationPerYear($acquisition_cost, $scrap_value, $est_useful_life);
        $depreciation_per_month = $this->getDepreciationPerMonth($acquisition_cost, $scrap_value, $est_useful_life);
        $custom_accumulated_cost = $this->getAccumulatedCost($depreciation_per_year, $custom_age);
        $remaining_book_value = $this->getRemainingBookValue($acquisition_cost, $custom_accumulated_cost);
        $total_depreciation = $this->getTotalDepreciation($custom_age, $depreciation_per_month);
        $yearly_depreciation_percent_yearly = $this->getDepreciationPercentYearly($depreciation_per_year, $acquisition_cost);
        $yearly_depreciation_percent_monthly = $this->getDepreciationPercentMonthly($depreciation_per_month, $acquisition_cost);
        $yearly_depreciation = $this->getDepreciationPerYearWithYear($depreciation_per_month, $depreciation_per_year, $custom_age, $start_depreciation, $custom_end_depreciation, $acquisition_cost);

        //if the custom end depreciation date is less than the start depreciation date
        if ($custom_end_depreciation < $start_depreciation) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'selected_end_depreciation' => [
                        'Invalid End Date Value.'
                    ]
                ]
            ], 422);
        }
//        elseif (($custom_age/12) > $est_useful_life){
//            return response()->json([
//                'message' => 'The given data was invalid.',
//                'errors' => [
//                    'selected_end_depreciation' => [
//                        'The selected end depreciation must be a date before or equal to the estimated useful life.'
//                    ]
//                ]
//            ], 422);
//        }


        $fixedAsset_arr = [
            'depreciation_method' => $depreciation_method,
            'est_useful_life' => $est_useful_life,
            'acquisition_date' => $acquisition_date,
            'acquisition_cost' => $acquisition_cost,
            'selected_age' => $custom_age,
            'scrap_value' => $scrap_value,
            'original_cost' => $original_cost,
            'accumulated_cost' => $custom_accumulated_cost,
            'start_depreciation' => $start_depreciation,
            'end_date' => $custom_end_depreciation,
//            'total_depreciation' => $total_depreciation,
            'depreciation_per_year' => $depreciation_per_year,
            'depreciation_per_month' => $depreciation_per_month,
            'remaining_book_value' => $remaining_book_value,
            'yearly_depreciation_percent' => $yearly_depreciation_percent_yearly . '%',
            'monthly_depreciation_percent' => $yearly_depreciation_percent_monthly . '%',
            'yearly_depreciation' => $yearly_depreciation,


        ];
        return response()->json([
            'message' => 'Fixed Asset calculated successfully.',
            'data' => $fixedAsset_arr
        ], 200);


    }

    //CALCULATIONS
    function getTotalDepreciation($custom_age, $depreciation_per_month)
    {
        return round($custom_age * $depreciation_per_month, 2);
    }

    function getDepreciationPerYear($acquisition_cost, $scrap_value, $est_useful_life)
    {
        //add two decimal places
        return round(($acquisition_cost - $scrap_value) / $est_useful_life, 2);
    }

    function getDepreciationPerMonth($acquisition_cost, $scrap_value, $est_useful_life)
    {
        return round(($acquisition_cost - $scrap_value) / ($est_useful_life * 12), 2);
    }

    function getDepreciationPercentYearly($depreciation_per_year, $acquisition_cost)
    {
        return round(($depreciation_per_year / $acquisition_cost) * 100, 2);
    }

    function getdepreciationPercentMonthly($depreciation_per_month, $acquisition_cost)
    {
        return round(($depreciation_per_month / $acquisition_cost) * 100, 2);
    }

    function getDepreciationPerYearWithYear($depreciation_per_month, $depreciation_per_year, $age, $start_depreciation, $custom_end_depreciation, $acquisition_cost)
    {
        $start_depreciation = Carbon::parse($start_depreciation);
        $end_depreciation = Carbon::parse($custom_end_depreciation);
        $start_depreciation_year = $start_depreciation->year;
        $start_depreciation_month = $start_depreciation->month;
        $custom_end_depreciation_year = $end_depreciation->year;
        $custom_end_depreciation_month = $end_depreciation->month;

        // Calculate the depreciation for a single month
        if ($start_depreciation_year == $custom_end_depreciation_year) {
            $depreciation = round($depreciation_per_month * $age, 2);
            //add total percentage of depreciation
//            $total_depreciation = $depreciation + $depreciation_per_year;
            $percentage = round(($depreciation / $acquisition_cost) * 100, 2);
            return [
                'year' => $start_depreciation_year,
                'depreciation' => $depreciation,
                'percentage' => $percentage . '%'
            ];
        }

        $yearly_depreciation = [];
        $total_percentage = 0;
        //get the start depreciation month
        $start_depreciation_month = 12 - ($start_depreciation_month - 1);
        $start_depreciation_month = $depreciation_per_month * $start_depreciation_month;
        $start_depreciation_month = round($start_depreciation_month, 2);
        $start_depreciation_month_percentage = round(($start_depreciation_month / $acquisition_cost) * 100, 2);


        //minus custom end depreciation month to 12
        $custom_end_depreciation_month = $depreciation_per_month * $custom_end_depreciation_month;
        $custom_end_depreciation_month = round($custom_end_depreciation_month, 2);
        $custom_end_depreciation_month_percentage = round(($custom_end_depreciation_month / $acquisition_cost) * 100, 2);


        //add the per year depreciation
        for ($i = $start_depreciation_year; $i <= $custom_end_depreciation_year; $i++) {
            if ($i == $custom_end_depreciation_year) {
                $yearly_depreciation[$i] = [
                    'depreciation' => $custom_end_depreciation_month,
                    'percentage' => $custom_end_depreciation_month_percentage
                ];
            } elseif ($i == $start_depreciation_year) {
                $yearly_depreciation[$i] = [
                    'depreciation' => $start_depreciation_month,
                    'percentage' => $start_depreciation_month_percentage

                ];
            } else {
                $yearly_depreciation[$i] = [
                    'depreciation' => $depreciation_per_year,
                    'percentage' => $this->getDepreciationPercentYearly($depreciation_per_year, $acquisition_cost)
                ];
            }
            //total percentage of depreciation
            $total_percentage += $yearly_depreciation[$i]['percentage'];
        }

        return [
            'yearly' => $yearly_depreciation,
            'total_percentage' => round($total_percentage, 2)
        ];
    }

    function getAccumulatedCost($depreciation_per_year, $age)
    {
        $age = $age / 12;
        return round($depreciation_per_year * $age, 2);
    }

    function getRemainingBookValue($acquisition_cost, $accumulated_cost)
    {
        return round($acquisition_cost - $accumulated_cost, 2);
    }

    function getAge($acquisition_date, $custom_end_depreciation = null)
    {
        $acquisition_date = Carbon::parse($acquisition_date);
        if ($custom_end_depreciation == null) {
            $custom_end_depreciation = Carbon::now();
            $age = ($acquisition_date->diffInMonths($custom_end_depreciation));
            return round($age, 3);
        } else {
            $custom_end_depreciation = Carbon::parse($custom_end_depreciation);
            $age = ($acquisition_date->diffInMonths($custom_end_depreciation));
            return round($age, 3);
        }
    }

    //Todo: CONCERN
    function getEndDepreciation($acquisition_date, $est_useful_life)
    {
        $acquisition_date = Carbon::parse($acquisition_date);
        $end_depreciation = $acquisition_date->addYears($est_useful_life);
        return $end_depreciation->format('Y-m');
    }

    function getStartDepreciation($acquisition_date)
    {
        $acquisition_date = Carbon::parse($acquisition_date);
        $start_depreciation = $acquisition_date->addMonth(1);
        return $start_depreciation->format('Y');
    }
}
