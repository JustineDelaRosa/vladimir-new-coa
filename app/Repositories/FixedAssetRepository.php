<?php

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\Company;
use App\Models\Formula;
use App\Models\Location;
use App\Models\SubCapex;
use App\Models\FixedAsset;
use App\Models\MajorCategory;
use App\Models\AdditionalCost;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Status\DepreciationStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Controllers\Masterlist\FixedAssetController;

class FixedAssetRepository
{
    use ApiResponse;

    protected $calculationRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
    }

    public function storeFixedAsset($request, $vladimirTagNumber, $departmentQuery)
    {
        $majorCategory = MajorCategory::withTrashed()->where('id', $request['major_category_id'])->first();
        $depreciationMethod = strtoupper($request['depreciation_method']);
        if ($depreciationMethod !== 'DONATION') {
            $depstatus = DepreciationStatus::where('id', $request['depreciation_status_id'])->first();
            //if the depreciation status name id Fully depreciated, run end depreciation to check the validity
            if ($depstatus->depreciation_status_name == 'Fully Depreciated') {
                //check if release date is not null
                if (isset($request['release_date'])) {
                    $end_depreciation = $this->calculationRepository->getEndDepreciation($this->calculationRepository->getStartDepreciation($request['release_date']), $majorCategory->est_useful_life, strtoupper($request['depreciation_method']) == 'STL' ? strtoupper($request['depreciation_method']) : ucwords(strtolower($request['depreciation_method'])));
                    //                    dd($end_depreciation);
                    //check if it really fully depreciated and passed the date today
                    if ($end_depreciation >= Carbon::now()) {
                        return 'Not yet fully depreciated';
                    }
                }
            }
        }

        //        return $request['release_date'] ?? Null;
        //        return $departmentQuery;

        $formula = Formula::create([
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'scrap_value' => $request['scrap_value'] ?? 0,
            'depreciable_basis' => $request['depreciable_basis'] ?? 0,
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'] ?? 0,
            'release_date' => $request['release_date'] ?? Null,
            'end_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getEndDepreciation(
                    $this->calculationRepository->getStartDepreciation($request['release_date']),
                    $majorCategory->est_useful_life,
                    strtoupper($request['depreciation_method']) == 'STL'
                        ? strtoupper($request['depreciation_method'])
                        : ucwords(strtolower($request['depreciation_method']))
                )
                : null,
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'start_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getStartDepreciation($request['release_date'])
                : null
        ]);
        $formula->fixedAsset()->create([
            'capex_id' => isset($request['sub_capex_id']) ? SubCapex::find($request['sub_capex_id'])->capex_id : null,
            'sub_capex_id' => $request['sub_capex_id'] ?? null,
            'vladimir_tag_number' => $vladimirTagNumber,
            'tag_number' => $request['tag_number'] ?? '-',
            'tag_number_old' => $request['tag_number_old'] ?? '-',
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'charged_department' => $request['department_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'] ?? '-')),
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'voucher_date' => $request['voucher_date'] ?? null,
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'is_old_asset' => $request['is_old_asset'] ?? 0,
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request['department_id'],
            'location_id' => $request['location_id'] ?? '-',
            'account_id' => $request['account_title_id'],
        ]);
        return $formula->fixedAsset->with('formula')->first();
    }

    public function updateFixedAsset($request, $departmentQuery, $id)
    {
        $majorCategory = MajorCategory::withTrashed()->where('id', $request['major_category_id'])->first();
        $depreciationMethod = strtoupper($request['depreciation_method']);
        if ($depreciationMethod !== 'DONATION') {
            $depstatus = DepreciationStatus::where('id', $request['depreciation_status_id'])->first();
            //if the depreciation status name id Fully depreciated, run end depreciation to check the validity
            if ($depstatus->depreciation_status_name == 'Fully Depreciated') {
                //check if release date is not null
                if (isset($request['release_date'])) {
                    $end_depreciation = $this->calculationRepository->getEndDepreciation($this->calculationRepository->getStartDepreciation($request['release_date']), $majorCategory->est_useful_life, strtoupper($request['depreciation_method']) == 'STL' ? strtoupper($request['depreciation_method']) : ucwords(strtolower($request['depreciation_method'])));
                    //                    dd($end_depreciation);
                    //check if it really fully depreciated and passed the date today
                    if ($end_depreciation >= Carbon::now()) {
                        return 'Not yet fully depreciated';
                    }
                }
            }
        }

        $fixedAsset = FixedAsset::find($id);
        $fixedAsset->update([
            'capex_id' => isset($request['sub_capex_id']) ? SubCapex::find($request['sub_capex_id'])->capex_id : null,
            'sub_capex_id' => $request['sub_capex_id'] ?? null,
            'tag_number' => $request['tag_number'] ?? '-',
            'tag_number_old' => $request['tag_number_old'] ?? '-',
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'charged_department' => $request['department_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'] ?? '-')),
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'voucher_date' => $request['voucher_date'] ?? null,
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'is_old_asset' => $request['is_old_asset'] ?? 0,
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request['department_id'],
            'location_id' => $request['location_id'] ?? '-',
            'account_id' => $request['account_title_id'],
            //            'print_count' => $request['print_count'] ?? $fixedAsset->print_count,
            //            'last_printed' => $request['print_count'] == $fixedAsset->print_count ? $fixedAsset->last_printed : Carbon::now(),
        ]);

        $fixedAsset->formula()->update([
            'depreciation_method' => strtoupper($request['depreciation_method']) == 'STL'
                ? strtoupper($request['depreciation_method'])
                : ucwords(strtolower($request['depreciation_method'])),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'scrap_value' => $request['scrap_value'] ?? 0,
            'depreciable_basis' => $request['depreciable_basis'] ?? 0,
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'] ?? 0,
            'release_date' => $request['release_date'] ?? Null,
            'end_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getEndDepreciation(
                    $this->calculationRepository->getStartDepreciation($request['release_date']),
                    $majorCategory->est_useful_life,
                    strtoupper($request['depreciation_method']) == 'STL'
                        ? strtoupper($request['depreciation_method'])
                        : ucwords(strtolower($request['depreciation_method']))
                )
                : null,
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'start_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getStartDepreciation($request['release_date'])
                : null
        ]);
        return $fixedAsset;
    }

    public function paginateResults($items, $page = null, $perPage = 15, $options = [])
    {
        $page = $page ?: (LengthAwarePaginator::resolveCurrentPage() ?: 1);

        $items = $items instanceof \Illuminate\Support\Collection ? $items : Collection::make($items);

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page,
            $options
        );

        $paginator->setPath(url()->current());

        return $paginator;
    }

    public function searchFixedAsset($search, $status, $page, $per_page = null, $filter = null)
    {
        $fixedAssetFields = [
            'id',
            'requester_id',
            'pr_number',
            'po_number',
            'rr_number',
            'warehouse_number_id',
            'capex_id',
            'sub_capex_id',
            'vladimir_tag_number',
            'tag_number',
            'tag_number_old',
            'from_request',
            'asset_description',
            'type_of_request_id',
            'asset_specification',
            'accountability',
            'accountable',
            'capitalized',
            'cellphone_number',
            'brand',
            'supplier_id',
            'major_category_id',
            'minor_category_id',
            'voucher',
            'voucher_date',
            'receipt',
            'quantity',
            'depreciation_method',
            'acquisition_cost',
            'asset_status_id',
            'cycle_count_status_id',
            'depreciation_status_id',
            'movement_status_id',
            'is_old_asset',
            'is_additional_cost',
            'is_active',
            'care_of',
            'company_id',
            'business_unit_id',
            'department_id',
            'charged_department',
            'location_id',
            'account_id',
            'remarks',
            'created_at',
            'print_count',
            'last_printed',
            DB::raw("NULL as add_cost_sequence"),
        ];

        $additionalCostFields = [
            'additional_costs.id',
            'additional_costs.requester_id',
            'additional_costs.pr_number',
            'additional_costs.po_number',
            'additional_costs.rr_number',
            'additional_costs.warehouse_number_id',
            'fixed_assets.capex_id AS capex_id',
            'fixed_assets.sub_capex_id AS sub_capex_id',
            'fixed_assets.vladimir_tag_number AS vladimir_tag_number',
            'fixed_assets.tag_number AS tag_number',
            'fixed_assets.tag_number_old AS tag_number_old',
            'additional_costs.from_request',
            'additional_costs.asset_description',
            'additional_costs.type_of_request_id',
            'additional_costs.asset_specification',
            'additional_costs.accountability',
            'additional_costs.accountable',
            'additional_costs.capitalized',
            'additional_costs.cellphone_number',
            'additional_costs.brand',
            'additional_costs.supplier_id',
            'additional_costs.major_category_id',
            'additional_costs.minor_category_id',
            'additional_costs.voucher',
            'additional_costs.voucher_date',
            'additional_costs.receipt',
            'additional_costs.quantity',
            'additional_costs.depreciation_method',
            'additional_costs.acquisition_cost',
            'additional_costs.asset_status_id',
            'additional_costs.cycle_count_status_id',
            'additional_costs.depreciation_status_id',
            'additional_costs.movement_status_id',
            'fixed_assets.is_old_asset as is_old_asset',
            'additional_costs.is_additional_cost',
            'additional_costs.is_active',
            'additional_costs.care_of',
            'additional_costs.company_id',
            'additional_costs.business_unit_id',
            'additional_costs.department_id',
            'fixed_assets.charged_department as charged_department',
            'additional_costs.location_id',
            'additional_costs.account_id',
            'additional_costs.remarks',
            'fixed_assets.created_at',
            'fixed_assets.print_count',
            'fixed_assets.last_printed',
            'additional_costs.add_cost_sequence',
        ];
        $firstQuery = ($status === 'deactivated')
            ? FixedAsset::onlyTrashed()->select($fixedAssetFields)
            : FixedAsset::select($fixedAssetFields);

        $secondQuery = ($status === 'deactivated')
            ? AdditionalCost::onlyTrashed()->select($additionalCostFields)->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            : AdditionalCost::select($additionalCostFields)->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id');

        if ($filter == "toDepreciate") {
            $firstQuery->where('depreciation_method', null)->where('is_released', 1);
            $secondQuery->where('additional_costs.depreciation_method', null)->where('additional_costs.is_released', 1);
        }

        if (!empty($search)) {
            $mainAttributesFixedAsset = [
                'vladimir_tag_number',
                'tag_number',
                'tag_number_old',
                'asset_description',
                'accountability',
                'accountable',
                'brand',
                'depreciation_method',
            ];

            $mainAttributesAdditionalCost = [
                'vladimir_tag_number',
                'tag_number',
                'tag_number_old',
            ];

            foreach ($mainAttributesFixedAsset as $attribute) {
                $firstQuery->orWhere($attribute, 'like', '%' . $search . '%');
            }

            foreach ($mainAttributesAdditionalCost as $attribute) {
                $secondQuery->orWhere($attribute, 'like', '%' . $search . '%');
            }

            $relationAttributes = [
                'subCapex' => ['sub_capex', 'sub_project'],
                'majorCategory' => ['major_category_name'],
                'minorCategory' => ['minor_category_name'],
                'department' => ['department_name'],
                'department.division' => ['division_name'],
                'assetStatus' => ['asset_status_name'],
                'typeOfRequest' => ['type_of_request_name'],
                'cycleCountStatus' => ['cycle_count_status_name'],
                'depreciationStatus' => ['depreciation_status_name'],
                'movementStatus' => ['movement_status_name'],
                'location' => ['location_name'],
                'company' => ['company_name'],
                'accountTitle' => ['account_title_name'],
            ];

            foreach ($relationAttributes as $relation => $attributes) {
                foreach ($attributes as $attribute) {
                    $firstQuery->orWhereHas($relation, function ($query) use ($attribute, $search) {
                        $query->where($attribute, 'like', '%' . $search . '%');
                    });

                    // Skip 'subCapex' when building the second query
                    if ($relation !== 'subCapex') {
                        $secondQuery->orWhereHas($relation, function ($query) use ($attribute, $search) {
                            $query->where($attribute, 'like', '%' . $search . '%');
                        });
                    }
                }
            }
        }



        $results = $firstQuery->unionAll($secondQuery)->orderBy('vladimir_tag_number', 'asc')->get();

        $results = $this->paginateResults($results, $page, $per_page);

        $results->setCollection($results->getCollection()->values());
        $results->getCollection()->transform(function ($item) {
            return $this->transformSearchFixedAsset($item);
        });
        return $results;
    }

    public function transformFixedAsset($fixed_asset): Collection
    {
        return collect($fixed_asset)->map(function ($asset) {
            return $this->transformSingleFixedAsset($asset);
        });
    }

    public function transformSingleFixedAsset($fixed_asset): array
    {

        $fixed_asset->additional_cost_count = $fixed_asset->additionalCost ? $fixed_asset->additionalCost->count() : 0;
        return [
            'total_cost' => $this->calculationRepository->getTotalCost($fixed_asset->additionalCost, $fixed_asset->acquisition_cost),
            'total_adcost' => $this->calculationRepository->getTotalCost($fixed_asset->additionalCost),
            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->first_name ?? '-',
                'last_name' => $fixed_asset->requestor->last_name ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'po_number' => $fixed_asset->po_number ?? '-',
            'rr_number' => $fixed_asset->rr_number ?? '-',
            'warehouse_number' => [
                'id' => $fixed_asset->warehouseNumber->id ?? '-',
                'warehouse_number' => $fixed_asset->warehouseNumber->warehouse_number ?? '-',
            ],
            'from_request' => $fixed_asset->from_request ?? '-',
            'can_release' => $fixed_asset->can_release ?? '-',
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
            'tag_number' => $fixed_asset->tag_number ?? '-',
            'tag_number_old' => $fixed_asset->tag_number_old ?? '-',
            'asset_description' => $fixed_asset->asset_description ?? '-',
            'type_of_request' => [
                'id' => $fixed_asset->typeOfRequest->id ?? '-',
                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $fixed_asset->asset_specification ?? '-',
            'accountability' => $fixed_asset->accountability ?? '-',
            'accountable' => $fixed_asset->accountable ?? '-',
            'cellphone_number' => $fixed_asset->cellphone_number ?? '-',
            'brand' => $fixed_asset->brand ?? '-',
            'supplier' => [
                'id' => $fixed_asset->supplier->id ?? '-',
                'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
                'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
            ],
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
            'voucher' => $fixed_asset->voucher ?? '-',
            'voucher_date' => $fixed_asset->voucher_date ?? '-',
            'receipt' => $fixed_asset->receipt ?? '-',
            'quantity' => $fixed_asset->quantity ?? '-',
            'depreciation_method' => $fixed_asset->depreciation_method ?? '-',
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date ?? '-',
            'acquisition_cost' => $fixed_asset->acquisition_cost ?? '-',
            'scrap_value' => $fixed_asset->formula->scrap_value ?? '-',
            'depreciable_basis' => $fixed_asset->formula->depreciable_basis ?? '-',
            'accumulated_cost' => $fixed_asset->formula->accumulated_cost ?? '-',
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
            'is_additional_cost' => $fixed_asset->is_additional_cost ?? '-',
            'is_old_asset' => $fixed_asset->is_old_asset ?? '-',
            'status' => $fixed_asset->is_active ?? '-',
            'care_of' => $fixed_asset->care_of ?? '-',
            'months_depreciated' => $fixed_asset->formula->months_depreciated ?? '-',
            'end_depreciation' => $fixed_asset->formula->end_depreciation ?? '-',
            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year ?? '-',
            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month ?? '-',
            'remaining_book_value' => $fixed_asset->formula->remaining_book_value ?? '-',
            'release_date' => $fixed_asset->formula->release_date ?? '-',
            'start_depreciation' => $fixed_asset->formula->start_depreciation ?? '-',
            'company' => [
                'id' => $fixed_asset->department->company->id ?? '-',
                'company_code' => $fixed_asset->department->company->company_code ?? '-',
                'company_name' => $fixed_asset->department->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->department->businessUnit->id ?? '-',
                'business_unit_code' => $fixed_asset->department->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->department->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'charged_department' => [
                'id' => $additional_cost->department->id ?? '-',
                'charged_department_code' => $additional_cost->department->department_code ?? '-',
                'charged_department_name' => $additional_cost->department->department_name ?? '-',
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
            'remarks' => $fixed_asset->remarks,
            'print_count' => $fixed_asset->print_count,
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $fixed_asset->last_printed,
            'tagging' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'additional_cost' => isset($fixed_asset->additionalCost) ? $fixed_asset->additionalCost->map(function ($additional_cost) {
                return [
                    'id' => $additional_cost->id ?? '-',
                    'requestor' => [
                        'id' => $additional_cost->requestor->id ?? '-',
                        'username' => $additional_cost->requestor->username ?? '-',
                        'first_name' => $additional_cost->requestor->first_name ?? '-',
                        'last_name' => $additional_cost->requestor->last_name ?? '-',
                        'employee_id' => $additional_cost->requestor->employee_id ?? '-',
                    ],
                    'pr_number' => $additional_cost->pr_number ?? '-',
                    'po_number' => $additional_cost->po_number ?? '-',
                    'rr_number' => $additional_cost->rr_number ?? '-',
                    'warehouse_number' => [
                        'id' => $additional_cost->warehouseNumber->id ?? '-',
                        'warehouse_number' => $additional_cost->warehouseNumber->warehouse_number ?? '-',
                    ],
                    'from_request' => $additional_cost->from_request ?? '-',
                    'can_release' => $additional_cost->can_release ?? '-',
                    'add_cost_sequence' => $additional_cost->add_cost_sequence ?? '-',
                    'asset_description' => $additional_cost->asset_description ?? '-',
                    'type_of_request' => [
                        'id' => $additional_cost->typeOfRequest->id ?? '-',
                        'type_of_request_name' => $additional_cost->typeOfRequest->type_of_request_name ?? '-',
                    ],
                    'asset_specification' => $additional_cost->asset_specification ?? '-',
                    'accountability' => $additional_cost->accountability ?? '-',
                    'accountable' => $additional_cost->accountable ?? '-',
                    'cellphone_number' => $additional_cost->cellphone_number ?? '-',
                    'brand' => $additional_cost->brand ?? '-',
                    'supplier' => [
                        'id' => $fixed_asset->supplier->id ?? '-',
                        'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
                        'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
                    ],
                    'division' => [
                        'id' => $additional_cost->department->division->id ?? '-',
                        'division_name' => $additional_cost->department->division->division_name ?? '-',
                    ],
                    'major_category' => [
                        'id' => $additional_cost->majorCategory->id ?? '-',
                        'major_category_name' => $additional_cost->majorCategory->major_category_name ?? '-',
                    ],
                    'minor_category' => [
                        'id' => $additional_cost->minorCategory->id ?? '-',
                        'minor_category_name' => $additional_cost->minorCategory->minor_category_name ?? '-',
                    ],
                    'est_useful_life' => $additional_cost->majorCategory->est_useful_life ?? '-',
                    'voucher' => $additional_cost->voucher ?? '-',
                    'voucher_date' => $additional_cost->voucher_date ?? '-',
                    'receipt' => $additional_cost->receipt ?? '-',
                    'quantity' => $additional_cost->quantity ?? '-',
                    'depreciation_method' => $additional_cost->depreciation_method ?? '-',
                    //                    'salvage_value' => $additional_cost->salvage_value,
                    'acquisition_date' => $additional_cost->acquisition_date ?? '-',
                    'acquisition_cost' => $additional_cost->acquisition_cost ?? '-',
                    'scrap_value' => $additional_cost->formula->scrap_value ?? '-',
                    'depreciable_basis' => $additional_cost->formula->depreciable_basis ?? '-',
                    'accumulated_cost' => $additional_cost->formula->accumulated_cost ?? '-',
                    'asset_status' => [
                        'id' => $additional_cost->assetStatus->id ?? '-',
                        'asset_status_name' => $additional_cost->assetStatus->asset_status_name ?? '-',
                    ],
                    'cycle_count_status' => [
                        'id' => $additional_cost->cycleCountStatus->id ?? '-',
                        'cycle_count_status_name' => $additional_cost->cycleCountStatus->cycle_count_status_name ?? '-',
                    ],
                    'depreciation_status' => [
                        'id' => $additional_cost->depreciationStatus->id ?? '-',
                        'depreciation_status_name' => $additional_cost->depreciationStatus->depreciation_status_name ?? '-',
                    ],
                    'movement_status' => [
                        'id' => $additional_cost->movementStatus->id ?? '-',
                        'movement_status_name' => $additional_cost->movementStatus->movement_status_name ?? '-',
                    ],
                    'is_additional_cost' => $additional_cost->is_additional_cost ?? '-',
                    'status' => $additional_cost->is_active ?? '-',
                    'care_of' => $additional_cost->care_of ?? '-',
                    'months_depreciated' => $additional_cost->formula->months_depreciated ?? '-',
                    'end_depreciation' => $additional_cost->formula->end_depreciation ?? '-',
                    'depreciation_per_year' => $additional_cost->formula->depreciation_per_year ?? '-',
                    'depreciation_per_month' => $additional_cost->formula->depreciation_per_month ?? '-',
                    'remaining_book_value' => $additional_cost->formula->remaining_book_value ?? '-',
                    'release_date' => $additional_cost->formula->release_date ?? '-',
                    'start_depreciation' => $additional_cost->formula->start_depreciation ?? '-',
                    'company' => [
                        'id' => $additional_cost->department->company->id ?? '-',
                        'company_code' => $additional_cost->department->company->company_code ?? '-',
                        'company_name' => $additional_cost->department->company->company_name ?? '-',
                    ],
                    'business_unit' => [
                        'id' => $fixed_asset->department->businessUnit->id ?? '-',
                        'business_unit_code' => $fixed_asset->department->businessUnit->business_unit_code ?? '-',
                        'business_unit_name' => $fixed_asset->department->businessUnit->business_unit_name ?? '-',
                    ],
                    'department' => [
                        'id' => $additional_cost->department->id ?? '-',
                        'department_code' => $additional_cost->department->department_code ?? '-',
                        'department_name' => $additional_cost->department->department_name ?? '-',
                    ],
                    'charged_department' => [
                        'id' => $additional_cost->department->id ?? '-',
                        'charged_department_code' => $additional_cost->department->department_code ?? '-',
                        'charged_department_name' => $additional_cost->department->department_name ?? '-',
                    ],
                    'location' => [
                        'id' => $additional_cost->location->id ?? '-',
                        'location_code' => $additional_cost->location->location_code ?? '-',
                        'location_name' => $additional_cost->location->location_name ?? '-',
                    ],
                    'account_title' => [
                        'id' => $additional_cost->accountTitle->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->account_title_name ?? '-',
                    ],
                    'remarks' => $additional_cost->remarks ?? '-',
                ];
            }) : [],
        ];
    }

    public function transformSearchFixedAsset($fixed_asset): array
    {
        $fixed_asset->additional_cost_count = $fixed_asset->additionalCost ? count($fixed_asset->additionalCost) : 0;
        return [
            //'totalCost' => $this->calculationRepository->getTotalCost($fixed_asset->acquisition_cost, $fixed_asset->additionalCost),
            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->first_name ?? '-',
                'last_name' => $fixed_asset->requestor->last_name ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'po_number' => $fixed_asset->po_number ?? '-',
            'rr_number' => $fixed_asset->rr_number ?? '-',
            'warehouse_number' => [
                'id' => $fixed_asset->warehouseNumber->id ?? '-',
                'warehouse_number' => $fixed_asset->warehouseNumber->warehouse_number ?? '-',
            ],
            'from_request' => $fixed_asset->from_request ?? '-',
            'can_release' => $fixed_asset->can_release ?? '-',
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
            'tag_number' => $fixed_asset->tag_number ?? '-',
            'tag_number_old' => $fixed_asset->tag_number_old ?? '-',
            'asset_description' => $fixed_asset->asset_description ?? '-',
            'type_of_request' => [
                'id' => $fixed_asset->typeOfRequest->id ?? '-',
                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $fixed_asset->asset_specification ?? '-',
            'accountability' => $fixed_asset->accountability ?? '-',
            'accountable' => $fixed_asset->accountable ?? '-',
            'cellphone_number' => $fixed_asset->cellphone_number ?? '-',
            'brand' => $fixed_asset->brand ?? '-',
            'supplier' => [
                'id' => $fixed_asset->supplier->id ?? '-',
                'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
                'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
            ],
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
            'voucher' => $fixed_asset->voucher ?? '-',
            'voucher_date' => $fixed_asset->voucher_date ?? '-',
            'receipt' => $fixed_asset->receipt ?? '-',
            'is_additional_cost' => $fixed_asset->is_additional_cost ?? '-',
            'status' => $fixed_asset->is_active ?? '-',
            'quantity' => $fixed_asset->quantity ?? '-',
            'depreciation_method' => $fixed_asset->depreciation_method ?? '-',
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date ?? '-',
            'acquisition_cost' => $fixed_asset->acquisition_cost ?? '-',
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
            'care_of' => $fixed_asset->care_of ?? '-',
            'company' => [
                'id' => $fixed_asset->department->company->id ?? '-',
                'company_code' => $fixed_asset->department->company->company_code ?? '-',
                'company_name' => $fixed_asset->department->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->department->businessUnit->id ?? '-',
                'business_unit_code' => $fixed_asset->department->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->department->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'charged_department' => [
                'id' => $additional_cost->department->id ?? '-',
                'charged_department_code' => $additional_cost->department->department_code ?? '-',
                'charged_department_name' => $additional_cost->department->department_name ?? '-',
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
            'remarks' => $fixed_asset->remarks ?? '-',
            'print_count' => $fixed_asset->print_count,
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $fixed_asset->last_printed ?? '-',
            'created_at' => $fixed_asset->created_at ?? '-',
            'add_cost_sequence' => $fixed_asset->add_cost_sequence ?? null,
        ];
    }




    //todo FASTER SEARCH
//    public function searchFixedAsset($search, $status, $page, $limit = null)
//    {
//        $fixedAssetFields = [
//'id',
//'requester_id',
//'pr_number',
//'po_number',
//'rr_number',
//'warehouse_number_id',
//'capex_id',
//'sub_capex_id',
//'vladimir_tag_number',
//'tag_number',
//'tag_number_old',
//'from_request',
//'asset_description',
//'type_of_request_id',
//'asset_specification',
//'accountability',
//'accountable',
//'capitalized',
//'cellphone_number',
//'brand',
//'supplier_id',
//'major_category_id',
//'minor_category_id',
//'voucher',
//'voucher_date',
//'receipt',
//'quantity',
//'depreciation_method',
//'acquisition_cost',
//'asset_status_id',
//'cycle_count_status_id',
//'depreciation_status_id',
//'movement_status_id',
//'is_old_asset',
//'is_additional_cost',
//'is_active',
//'care_of',
//'company_id',
//'business_unit_id',
//'department_id',
//'charged_department',
//'location_id',
//'account_id',
//'remarks',
//'created_at',
//'print_count',
//'last_printed',
//DB::raw("NULL as add_cost_sequence"),
//        ];
//
//        $additionalCostFields = [
//            'additional_costs.id',
//            'additional_costs.requester_id',
//            'additional_costs.pr_number',
//            'additional_costs.po_number',
//            'additional_costs.rr_number',
//            'additional_costs.warehouse_number_id',
//            'fixed_assets.capex_id AS capex_id',
//            'fixed_assets.sub_capex_id AS sub_capex_id',
//            'fixed_assets.vladimir_tag_number AS vladimir_tag_number',
//            'fixed_assets.tag_number AS tag_number',
//            'fixed_assets.tag_number_old AS tag_number_old',
//            'additional_costs.from_request',
//            'additional_costs.asset_description',
//            'additional_costs.type_of_request_id',
//            'additional_costs.asset_specification',
//            'additional_costs.accountability',
//            'additional_costs.accountable',
//            'additional_costs.capitalized',
//            'additional_costs.cellphone_number',
//            'additional_costs.brand',
//            'additional_costs.supplier_id',
//            'additional_costs.major_category_id',
//            'additional_costs.minor_category_id',
//            'additional_costs.voucher',
//            'additional_costs.voucher_date',
//            'additional_costs.receipt',
//            'additional_costs.quantity',
//            'additional_costs.depreciation_method',
//            'additional_costs.acquisition_cost',
//            'additional_costs.asset_status_id',
//            'additional_costs.cycle_count_status_id',
//            'additional_costs.depreciation_status_id',
//            'additional_costs.movement_status_id',
//            'fixed_assets.is_old_asset as is_old_asset',
//            'additional_costs.is_additional_cost',
//            'additional_costs.is_active',
//            'additional_costs.care_of',
//            'additional_costs.company_id',
//            'additional_costs.business_unit_id',
//            'additional_costs.department_id',
//            'fixed_assets.charged_department as charged_department',
//            'additional_costs.location_id',
//            'additional_costs.account_id',
//            'additional_costs.remarks',
//            'fixed_assets.created_at',
//            'fixed_assets.print_count',
//            'fixed_assets.last_printed',
//            'additional_costs.add_cost_sequence',
//        ];
//        $firstQuery = ($status === 'deactivated')
//            ? FixedAsset::onlyTrashed()->select($fixedAssetFields)
//            : FixedAsset::select($fixedAssetFields);
//
//        $secondQuery = ($status === 'deactivated')
//            ? AdditionalCost::onlyTrashed()->select($additionalCostFields)->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
//            : AdditionalCost::select($additionalCostFields)->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id');
//
//
//
//        if (!empty($search)) {
//            $mainAttributesFixedAsset = [
//                'vladimir_tag_number',
//                'tag_number',
//                'tag_number_old',
//                'asset_description',
//                'accountability',
//                'accountable',
//                'brand',
//                'depreciation_method',
//            ];
//
//            $mainAttributesAdditionalCost = [
//                'vladimir_tag_number',
//                'tag_number',
//                'tag_number_old',
//            ];
//
//            foreach ($mainAttributesFixedAsset as $attribute) {
//                $firstQuery->orWhere($attribute, 'like', '%' . $search . '%');
//            }
//
//            foreach ($mainAttributesAdditionalCost as $attribute) {
//                $secondQuery->orWhere($attribute, 'like', '%' . $search . '%');
//            }
//
//            $relationAttributes = [
//                'subCapex' => ['sub_capex', 'sub_project'],
//                'majorCategory' => ['major_category_name'],
//                'minorCategory' => ['minor_category_name'],
//                'department' => ['department_name'],
//                'department.division' => ['division_name'],
//                'assetStatus' => ['asset_status_name'],
//                'typeOfRequest' => ['type_of_request_name'],
//                'cycleCountStatus' => ['cycle_count_status_name'],
//                'depreciationStatus' => ['depreciation_status_name'],
//                'movementStatus' => ['movement_status_name'],
//                'location' => ['location_name'],
//                'company' => ['company_name'],
//                'accountTitle' => ['account_title_name'],
//            ];
//
//            foreach ($relationAttributes as $relation => $attributes) {
//                foreach ($attributes as $attribute) {
//                    $firstQuery->orWhereHas($relation, function ($query) use ($attribute, $search) {
//                        $query->where($attribute, 'like', '%' . $search . '%');
//                    });
//
//                    // Skip 'subCapex' when building the second query
//                    if ($relation !== 'subCapex') {
//                        $secondQuery->orWhereHas($relation, function ($query) use ($attribute, $search) {
//                            $query->where($attribute, 'like', '%' . $search . '%');
//                        });
//                    }
//                }
//            }
//        }
//
//        $results = $firstQuery->unionAll($secondQuery)->orderBy('vladimir_tag_number', 'asc')->get();
//
//        $results = $this->paginateResults($results, $limit, $page);
//
//        $results->setCollection($results->getCollection()->values());
//        $results->getCollection()->transform(function ($item) {
//            return $this->transformSearchFixedAsset($item);
//        });
//        return $results;
//    }
}
