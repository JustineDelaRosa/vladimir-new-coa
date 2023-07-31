<?php

namespace App\Repositories;

use App\Http\Controllers\Masterlist\FixedAssetController;
use App\Models\AdditionalCost;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\SubCapex;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FixedAssetRepository
{

    protected $calculationRepository;

    public function __construct() {
        $this->calculationRepository = new CalculationRepository();
    }

    public function storeFixedAsset($request, $vladimirTagNumber, $departmentQuery)
    {

        $majorCategory = MajorCategory::withTrashed()->where('id', $request['major_category_id'])->first();
        $fixedAsset = FixedAsset::create([
            'capex_id' => isset($request['sub_capex_id']) ? SubCapex::find($request['sub_capex_id'])->capex_id : null,
            'sub_capex_id' => $request['sub_capex_id'] ?? null,
            'vladimir_tag_number' => $vladimirTagNumber,
            'tag_number' => $request['tag_number'] ?? '-',
            'tag_number_old' => $request['tag_number_old'] ?? '-',
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' => $request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'])) ?? '-',
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => $request['depreciation_method'],
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'is_old_asset' => $request['is_old_asset'] ?? 0,
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request['department_id'],
            'location_id' => Location::where('sync_id', $departmentQuery->location_sync_id)->first()->id ?? null,
            'account_id' => $request['account_title_id'],
        ]);

        $fixedAsset->formula()->create([
            'depreciation_method' => $request['depreciation_method'],
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'scrap_value' => $request['scrap_value'],
            'depreciable_basis' => $request['depreciable_basis'],
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'],
            'end_depreciation' => $this->calculationRepository->getEndDepreciation($this->calculationRepository->getStartDepreciation($request['release_date']), $majorCategory->est_useful_life, $request['depreciation_method']),
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'release_date' => $request['release_date'],
            'start_depreciation' => $this->calculationRepository->getStartDepreciation($request['release_date'])
        ]);

        return $fixedAsset;
    }

    public function updateFixedAsset($request, $departmentQuery, $id)
    {
        $majorCategory = MajorCategory::withTrashed()->where('id', $request['major_category_id'])->first();
        $fixedAsset = FixedAsset::find($id);
        $fixedAsset->update([
            'capex_id' => isset($request['sub_capex_id']) ? SubCapex::find($request['sub_capex_id'])->capex_id : null,
            'sub_capex_id' => $request['sub_capex_id'] ?? null,
            'tag_number' => $request['tag_number'] ?? '-',
            'tag_number_old' => $request['tag_number_old'] ?? '-',
            'asset_description' => $request['asset_description'],
            'type_of_request_id' => $request['type_of_request_id'],
            'asset_specification' => $request['asset_specification'],
            'accountability' => $request['accountability'],
            'accountable' =>$request['accountable'] ?? '-',
            'cellphone_number' => $request['cellphone_number'] ?? '-',
            'brand' => ucwords(strtolower($request['brand'])) ?? '-',
            'major_category_id' => $request['major_category_id'],
            'minor_category_id' => $request['minor_category_id'],
            'voucher' => $request['voucher'] ?? '-',
            'receipt' => $request['receipt'] ?? '-',
            'quantity' => $request['quantity'],
            'depreciation_method' => $request['depreciation_method'],
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'asset_status_id' => $request['asset_status_id'],
            'depreciation_status_id' => $request['depreciation_status_id'],
            'cycle_count_status_id' => $request['cycle_count_status_id'],
            'movement_status_id' => $request['movement_status_id'],
            'is_old_asset' => $request['is_old_asset'] ?? 0,
            'care_of' => ucwords(strtolower($request['care_of'] ?? '-')),
            'company_id' => Company::where('sync_id', $departmentQuery->company_sync_id)->first()->id ?? null,
            'department_id' => $request['department_id'],
            'location_id' => Location::where('sync_id', $departmentQuery->location_sync_id)->first()->id ?? null,
            'account_id' => $request['account_title_id'],
        ]);

        $fixedAsset->formula()->update([
            'depreciation_method' => $request['depreciation_method'],
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'],
            'scrap_value' => $request['scrap_value'],
            'depreciable_basis' => $request['depreciable_basis'],
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'],
            'end_depreciation' => $this->calculationRepository->getEndDepreciation($this->calculationRepository->getStartDepreciation($request['release_date']), $majorCategory->est_useful_life,$request['depreciation_method']),
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'release_date' => $request['release_date'],
            'start_depreciation' => $this->calculationRepository->getStartDepreciation($request['release_date'])
        ]);
        return $fixedAsset;
    }

    public function paginateResults($items, $perPage = 15, $page = null, $options = [])
    {
        $page = $page ?: (LengthAwarePaginator::resolveCurrentPage() ?: 1);

        $items = $items instanceof \Illuminate\Support\Collection ? $items : Collection::make($items);

        $paginator = new LengthAwarePaginator($items->forPage($page, $perPage),
            $items->count(), $perPage, $page, $options);

        $paginator->setPath(url()->current());

        return $paginator;
    }

    public function searchFixedAsset($search, $limit = null, $page)
    {
        $firstQuery = FixedAsset::select([
                'id',
                'capex_id',
                'sub_capex_id',
                'vladimir_tag_number',
                'tag_number',
                'tag_number_old',
                'asset_description',
                'type_of_request_id',
                'asset_specification',
                'accountability',
                'accountable',
                'capitalized',
                'cellphone_number',
                'brand',
                'major_category_id',
                'minor_category_id',
                'voucher',
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
                'care_of',
                'company_id',
                'department_id',
                'location_id',
                'account_id',
                'created_at',
            ]);

        $secondQuery = AdditionalCost::select([
                'additional_costs.id',
                'fixed_assets.capex_id AS capex_id',
                'fixed_assets.sub_capex_id AS sub_capex_id',
                'fixed_assets.vladimir_tag_number AS vladimir_tag_number',
                'fixed_assets.tag_number AS tag_number',
                'fixed_assets.tag_number_old AS tag_number_old',
                'additional_costs.asset_description',
                'additional_costs.type_of_request_id',
                'additional_costs.asset_specification',
                'additional_costs.accountability',
                'additional_costs.accountable',
                'additional_costs.capitalized',
                'additional_costs.cellphone_number',
                'additional_costs.brand',
                'additional_costs.major_category_id',
                'additional_costs.minor_category_id',
                'additional_costs.voucher',
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
                'additional_costs.care_of',
                'additional_costs.company_id',
                'additional_costs.department_id',
                'additional_costs.location_id',
                'additional_costs.account_id',
                'fixed_assets.created_at'
            ])->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id');


        $results = $firstQuery->unionAll($secondQuery)->orderBy('vladimir_tag_number', 'desc')->get();

//if search is not empty
        if(!empty($search)){
            $results = $results->filter(function ($item) use ($search) {
                // Using stripos for multiple checks similar to 'LIKE' operator in SQL
                $mainConditionChecked = stripos($item->vladimir_tag_number, $search) !== false ||
                    stripos($item->tag_number, $search) !== false ||
                    stripos($item->tag_number_old, $search) !== false ||
                    stripos($item->type_of_request_id, $search) !== false ||
                    stripos($item->accountability, $search) !== false ||
                    stripos($item->accountable, $search) !== false ||
                    stripos($item->brand, $search) !== false ||
                    stripos($item->depreciation_method, $search) !== false;

                $relationConditionChecked = (isset($item->subCapex) && (stripos($item->subCapex->sub_capex, $search) !== false || stripos($item->subCapex->sub_project, $search) !== false)) ||
                    (isset($item->majorCategory) && stripos($item->majorCategory->major_category_name, $search) !== false) ||
                    (isset($item->minorCategory) && stripos($item->minorCategory->minor_category_name, $search) !== false) ||
                    (isset($item->department->division) && stripos($item->department->division->division_name, $search) !== false) ||
                    (isset($item->assetStatus) && stripos($item->assetStatus->asset_status_name, $search) !== false) ||
                    (isset($item->cycleCountStatus) && stripos($item->cycleCountStatus->cycle_count_status_name, $search) !== false) ||
                    (isset($item->depreciationStatus) && stripos($item->depreciationStatus->depreciation_status_name, $search) !== false) ||
                    (isset($item->movementStatus) && stripos($item->movementStatus->movement_status_name, $search) !== false) ||
                    (isset($item->location) && stripos($item->location->location_name, $search) !== false) ||
                    (isset($item->company) && stripos($item->company->company_name, $search) !== false) ||
                    (isset($item->department) && stripos($item->department->department_name, $search) !== false) ||
                    (isset($item->accountTitle) && stripos($item->accountTitle->account_title_name, $search) !== false);

                return $mainConditionChecked || $relationConditionChecked;
            });
        }

        $results = $this->paginateResults($results, $limit,$page);

        $results->setCollection($results->getCollection()->values());
                $results->getCollection()->transform(function ($item) {
            return $this->transformSearchFixedAsset($item);
        });
        return $results;
    }

    public function transformFixedAsset($fixed_asset): array
    {
        $fixed_assets_arr = [];
        foreach ($fixed_asset as $asset) {
            // Transform the current asset using the transformSingleFixedAsset method
            $fixed_assets_arr [] = $this->transformSingleFixedAsset($asset);
        }
        return $fixed_assets_arr;
    }

    public function transformSingleFixedAsset($fixed_asset): array
    {
        return [
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
            'brand' => $fixed_asset->brand ?? '-',
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
            'voucher' => $fixed_asset->voucher,
            'receipt' => $fixed_asset->receipt,
            'quantity' => $fixed_asset->quantity,
            'depreciation_method' => $fixed_asset->depreciation_method,
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date,
            'acquisition_cost' => $fixed_asset->acquisition_cost,
            'scrap_value' => $fixed_asset->formula->scrap_value,
            'depreciable_basis' => $fixed_asset->formula->depreciable_basis,
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
            'months_depreciated' => $fixed_asset->formula->months_depreciated,
            'end_depreciation' => $fixed_asset->formula->end_depreciation,
            'depreciation_per_year' => $fixed_asset->formula->depreciation_per_year,
            'depreciation_per_month' => $fixed_asset->formula->depreciation_per_month,
            'remaining_book_value' => $fixed_asset->formula->remaining_book_value,
            'release_date' => $fixed_asset->formula->release_date,
            'start_depreciation' => $fixed_asset->formula->start_depreciation,
            'company' => [
                'id' => $fixed_asset->department->company->id ?? '-',
                'company_code' => $fixed_asset->department->company->company_code ?? '-',
                'company_name' => $fixed_asset->department->company->company_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->department->location->id ?? '-',
                'location_code' => $fixed_asset->department->location->location_code ?? '-',
                'location_name' => $fixed_asset->department->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $fixed_asset->accountTitle->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->account_title_name ?? '-',
            ],
        ];
    }

    public function transformSearchFixedAsset($fixed_asset): array
    {
        return [
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
            'brand' => $fixed_asset->brand ?? '-',
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
            'voucher' => $fixed_asset->voucher,
            'receipt' => $fixed_asset->receipt,
            'is_additional_cost' => $fixed_asset->is_additional_cost,
            'quantity' => $fixed_asset->quantity,
            'depreciation_method' => $fixed_asset->depreciation_method,
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date,
            'acquisition_cost' => $fixed_asset->acquisition_cost,
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
            'care_of' => $fixed_asset->care_of,
            'company' => [
                'id' => $fixed_asset->department->company->id ?? '-',
                'company_code' => $fixed_asset->department->company->company_code ?? '-',
                'company_name' => $fixed_asset->department->company->company_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->department->location->id ?? '-',
                'location_code' => $fixed_asset->department->location->location_code ?? '-',
                'location_name' => $fixed_asset->department->location->location_name ?? '-',
            ],
            'account_title' => [
                'id' => $fixed_asset->accountTitle->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->account_title_name ?? '-',
            ],
        ];
    }
}
