<?php

namespace App\Repositories;

use App\Models\AccountingEntries;
use App\Models\AssetApproval;
use App\Models\AssetTransferRequest;
use App\Models\MinorCategory;
use App\Models\PoBatch;
use App\Models\TypeOfRequest;
use Carbon\Carbon;
use App\Models\Company;
use App\Models\Formula;
use App\Models\Location;
use App\Models\SubCapex;
use App\Models\FixedAsset;
use App\Models\MajorCategory;
use App\Models\AdditionalCost;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
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

    public function storeFixedAsset($request, $vladimirTagNumber, $businessUnitQuery)
    {
        $majorCategory = $this->getMajorCategory($request['major_category_id']);
        $this->checkDepreciationStatus($request, $majorCategory);

        $formulaData = $this->prepareFormulaDataForStore($request, $majorCategory);
        $formula = Formula::create($formulaData);

        $fixedAssetData = $this->prepareFixedAssetDataForStore($request, $vladimirTagNumber, $businessUnitQuery);
        $formula->fixedAsset()->create($fixedAssetData);

        return $formula->fixedAsset->with('formula')->first();
    }

    private function prepareFormulaDataForStore($request, $majorCategory): array
    {
        $depreciationMethod = strtoupper($request['depreciation_method']);
        return [
            'depreciation_method' => $depreciationMethod == 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod)),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'scrap_value' => $request['scrap_value'] ?? 0,
            'depreciable_basis' => $request['depreciable_basis'] ?? 0,
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'] ?? 0,
            'release_date' => $request['release_date'] ?? Null,
            'end_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getEndDepreciation(
                    $this->calculationRepository->getStartDepreciation($request['depreciation_method'], $request['release_date']),
//                    $this->calculationRepository->getStartDepreciation($request['voucher_date']),
                    $majorCategory->est_useful_life,
                    $depreciationMethod == 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod))
                )
                : null,
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'start_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
//                ? $this->calculationRepository->getStartDepreciation($request['voucher_date'])
                ? $this->calculationRepository->getStartDepreciation($request['depreciation_method'], $request['release_date'])
                : null
        ];
    }

    private function prepareFixedAssetDataForStore($request, $vladimirTagNumber, $businessUnitQuery): array
    {
        $accountingEntry = MinorCategory::where('id', $request['minor_category_id'])->first()->accounting_entries_id;
        return [
            'capex_id' => isset($request['sub_capex_id']) ? SubCapex::find($request['sub_capex_id'])->capex_id : null,
            'sub_capex_id' => $request['sub_capex_id'] ?? null,
            'vladimir_tag_number' => $vladimirTagNumber,
            'tag_number' => $request['tag_number'] ?? '-',
//            'requester_id' => $request['requester_id'],
//            'supplier_id' => $request['supplier_id'],
//            'po_number' => $request['po_number'],
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
            'company_id' => Company::where('sync_id', $businessUnitQuery->company_sync_id)->first()->id ?? null,
            'business_unit_id' => $request['business_unit_id'],
            'department_id' => $request['department_id'],
            'unit_id' => $request['unit_id'],
            'subunit_id' => $request['subunit_id'] ?? '-',
            'location_id' => $request['location_id'] ?? '-',
            'account_id' => $accountingEntry,
            'uom_id' => $request['uom_id'] ?? null,
        ];
    }

    //UPDATING FIXED ASSET
    public function updateFixedAsset($request, $businessUnitQuery, $id)
    {
        $majorCategory = $this->getMajorCategory($request['major_category_id']);
        $this->checkDepreciationStatus($request, $majorCategory);

        $fixedAsset = FixedAsset::find($id);
        $fixedAssetData = $this->prepareFixedAssetDataForUpdate($request, $businessUnitQuery, $id);
        $fixedAsset->update($fixedAssetData);

        $formulaData = $this->prepareFormulaDataForUpdate($request, $majorCategory);
        $fixedAsset->formula()->update($formulaData);

        return $fixedAsset;
    }

    private function prepareFixedAssetDataForUpdate($request, $businessUnitQuery, $id): array
    {
        $accountingEntry = MinorCategory::where('id', $request['minor_category_id'])->first()->accounting_entries_id;
        return [
            'po_number' => $request['po_number'],
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
            'company_id' => Company::where('sync_id', $businessUnitQuery->company_sync_id)->first()->id ?? null,
            'business_unit_id' => $request['business_unit_id'],
            'department_id' => $request['department_id'],
            'unit_id' => $request['unit_id'],
            'subunit_id' => $request['subunit_id'] ?? '-',
            'location_id' => $request['location_id'] ?? '-',
            'account_id' => $accountingEntry,
            'uom_id' => $request['uom_id'] ?? null,
        ];
    }

    private function prepareFormulaDataForUpdate($request, $majorCategory): array
    {
        $depreciationMethod = strtoupper($request['depreciation_method']);
        return [
            'depreciation_method' => $depreciationMethod == 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod)),
            'acquisition_date' => $request['acquisition_date'],
            'acquisition_cost' => $request['acquisition_cost'] ?? 0,
            'scrap_value' => $request['scrap_value'] ?? 0,
            'depreciable_basis' => $request['depreciable_basis'] ?? 0,
            'accumulated_cost' => $request['accumulated_cost'] ?? 0,
            'months_depreciated' => $request['months_depreciated'] ?? 0,
            'release_date' => $request['release_date'] ?? Null,
            'end_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
                ? $this->calculationRepository->getEndDepreciation(
//                    $this->calculationRepository->getStartDepreciation($request['voucher_date']),
                    $this->calculationRepository->getStartDepreciation($request['depreciation_method'], $request['release_date']),
                    $majorCategory->est_useful_life,
                    $depreciationMethod == 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod))
                )
                : null,
            'depreciation_per_year' => $request['depreciation_per_year'] ?? 0,
            'depreciation_per_month' => $request['depreciation_per_month'] ?? 0,
            'remaining_book_value' => $request['remaining_book_value'] ?? 0,
            'start_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
//                ? $this->calculationRepository->getStartDepreciation($request['voucher_date'])
                ? $this->calculationRepository->getStartDepreciation($request['depreciation_method'], $request['release_date'])
                : null
        ];
    }

    private function getMajorCategory($id)
    {
        return MajorCategory::withTrashed()->where('id', $id)->first();
    }

    private function checkDepreciationStatus($request, $majorCategory)
    {
        $depreciationMethod = strtoupper($request['depreciation_method']);
        if ($depreciationMethod !== 'DONATION') {
            $depstatus = DepreciationStatus::where('id', $request['depreciation_status_id'])->first();
            if ($depstatus->depreciation_status_name == 'Fully Depreciated' && isset($request['release_date'])) {
                $end_depreciation = $this->calculationRepository->getEndDepreciation(
//                    $this->calculationRepository->getStartDepreciation($request['voucher_date']),
                    $this->calculationRepository->getStartDepreciation($request['depreciation_method'], $request['release_date']),
                    $majorCategory->est_useful_life,
                    $depreciationMethod == 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod))
                );
                if ($end_depreciation >= Carbon::now()) {
                    return 'Not yet fully depreciated';
                }
            }
        }
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
        $filter = $filter ? array_map('trim', explode(',', $filter)) : [];
        //check if filter only contains 'With Voucher'
        if (count($filter) == 1 && $filter[0] == 'With Voucher') {
            return $this->faWithVoucherView($page, $per_page);
        }

        $firstQuery = ($status === 'deactivated')
            ? FixedAsset::onlyTrashed()->select($this->fixedAssetFields())
            : FixedAsset::select($this->fixedAssetFields());

        $secondQuery = ($status === 'deactivated')
            ? AdditionalCost::onlyTrashed()->select($this->additionalCostFields())->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            : AdditionalCost::select($this->additionalCostFields())->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id');

        $smallToolsId = TypeOfRequest::where('type_of_request_name', 'Small Tools')->first()->id;
        $conditions = [
            'To Depreciate' => ['depreciation_method' => null, 'is_released' => 1],
            'Fixed Asset' => ['is_additional_cost' => 0],
            'Additional Cost' => ['is_additional_cost' => 1],
            'From Request' => ['from_request' => 1],
            'Small Tools' => ['type_of_request_id' => $smallToolsId],
        ];

        if (!empty($filter)) {
            $this->applyFilters($firstQuery, $filter, $conditions);
            $this->applyFilters($secondQuery, $filter, $conditions, 'additional_costs');
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
                'transaction_number',
                'reference_number',
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
                'accountTitle.initialCredit' => ['account_title_name'],
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


        $results = $firstQuery->unionAll($secondQuery)->orderBy('asset_description')->get();

        $results = $this->paginateResults($results, $page, $per_page);

        $results->setCollection($results->getCollection()->values());
        $results->getCollection()->transform(function ($item) {
            return $this->transformSearchFixedAsset($item);
        });
        return $results;
    }

    function applyFilters($query, $filter, $conditions, $prefix = '')
    {
        $query->where(function ($query) use ($filter, $conditions, $prefix) {
            foreach ($filter as $key) {
                if (isset($conditions[$key])) {
                    $query->orWhere(function ($query) use ($conditions, $key, $prefix) {
                        foreach ($conditions[$key] as $field => $value) {
                            $field = $prefix ? $prefix . '.' . $field : $field;
                            if (is_array($value)) {
                                $query->where($field, $value[0], $value[1]);
                            } else {
                                $query->where($field, $value);
                            }
                        }
                    });
                }
            }
        });
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
            'can_add' => $fixed_asset->is_released ? 1 : 0,
            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'transaction_number' => $fixed_asset->transaction_number,
            'reference_number' => $fixed_asset->reference_number,
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->first_name ?? '-',
                'last_name' => $fixed_asset->requestor->last_name ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'is_released' => $fixed_asset->is_released,
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'po_number' => $fixed_asset->po_number ?? '-',
            'rr_number' => $fixed_asset->rr_number ?? '-',
            'warehouse_number' => [
                'id' => $fixed_asset->warehouseNumber->id ?? '-',
                'warehouse_number' => $fixed_asset->warehouseNumber->warehouse_number ?? '-',
            ],
            'warehouse' => [
                'id' => $fixed_asset->warehouse->id ?? '-',
                'warehouse_name' => $fixed_asset->warehouse->warehouse_name ?? '-',
            ],
            'inclusion' => $fixed_asset->inclusion ?? null,
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
            'capex_number' => $fixed_asset->capex_number ?? '-',
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
            'unit_of_measure' => [
                'id' => $fixed_asset->uom->id ?? '-',
                'uom_code' => $fixed_asset->uom->uom_code ?? '-',
                'uom_name' => $fixed_asset->uom->uom_name ?? '-',
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
            'asset_status' =>[
                'id' => $fixed_asset->assetStatus->id ?? '-',
                'asset_status_name' => $fixed_asset->from_request ? ($fixed_asset->is_released ? $fixed_asset->assetStatus->asset_status_name : 'For Releasing') : $fixed_asset->assetStatus->asset_status_name ?? '-',
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
                'id' => $fixed_asset->company->id ?? '-',
                'company_code' => $fixed_asset->company->company_code ?? '-',
                'company_name' => $fixed_asset->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->businessUnit->id ?? '-',
                'business_unit_code' => $fixed_asset->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'unit' => [
                'id' => $fixed_asset->unit->id ?? '-',
                'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                'unit_name' => $fixed_asset->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $fixed_asset->subunit->id ?? '-',
                'subunit_code' => $fixed_asset->subunit->sub_unit_code ?? '-',
                'subunit_name' => $fixed_asset->subunit->sub_unit_name ?? '-',
            ],
            'charged_department' => [
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
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
            ],
            'initial_debit' => [
                'id' => $fixed_asset->accountTitle->initialDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialDebit->account_title_name ?? '-',
            ],
            'initial_credit' => [
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
            ],
            'depreciation_debit' => [
                'id' => $fixed_asset->accountTitle->depreciationDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationDebit->account_title_name ?? '-',
            ],
            'depreciation_credit' => [
                'id' => $fixed_asset->accountTitle->depreciationCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->account_title_name ?? '-',
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
                    'warehouse' => [
                        'id' => $additional_cost->warehouse->id ?? '-',
                        'warehouse_name' => $additional_cost->warehouse->warehouse_name ?? '-',
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
                        'id' => $additional_cost->supplier->id ?? '-',
                        'supplier_code' => $additional_cost->supplier->supplier_code ?? '-',
                        'supplier_name' => $additional_cost->supplier->supplier_name ?? '-',
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
                    'unit_of_measure' => [
                        'id' => $additional_cost->uom->id ?? '-',
                        'uom_code' => $additional_cost->uom->uom_code ?? '-',
                        'uom_name' => $additional_cost->uom->uom_name ?? '-',
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
                        'id' => $additional_cost->company->id ?? '-',
                        'company_code' => $additional_cost->company->company_code ?? '-',
                        'company_name' => $additional_cost->company->company_name ?? '-',
                    ],
                    'business_unit' => [
                        'id' => $additional_cost->businessUnit->id ?? '-',
                        'business_unit_code' => $additional_cost->businessUnit->business_unit_code ?? '-',
                        'business_unit_name' => $additional_cost->businessUnit->business_unit_name ?? '-',
                    ],
                    'department' => [
                        'id' => $additional_cost->department->id ?? '-',
                        'department_code' => $additional_cost->department->department_code ?? '-',
                        'department_name' => $additional_cost->department->department_name ?? '-',
                    ],
                    'unit' => [
                        'id' => $additional_cost->unit->id ?? '-',
                        'unit_code' => $additional_cost->unit->unit_code ?? '-',
                        'unit_name' => $additional_cost->unit->unit_name ?? '-',
                    ],
                    'subunit' => [
                        'id' => $additional_cost->subunit->id ?? '-',
                        'subunit_code' => $additional_cost->subunit->sub_unit_code ?? '-',
                        'subunit_name' => $additional_cost->subunit->sub_unit_name ?? '-',
                    ],
                    'charged_department' => [
                        'id' => $additional_cost->department->id ?? '-',
                        'department_code' => $additional_cost->department->department_code ?? '-',
                        'department_name' => $additional_cost->department->department_name ?? '-',
                    ],
                    'location' => [
                        'id' => $additional_cost->location->id ?? '-',
                        'location_code' => $additional_cost->location->location_code ?? '-',
                        'location_name' => $additional_cost->location->location_name ?? '-',
                    ],
                    'account_title' => [
                        'id' => $additional_cost->accountTitle->initialCredit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->initialCredit->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->initialCredit->account_title_name ?? '-',
                    ],
                    'initial_debit' => [
                        'id' => $additional_cost->accountTitle->initialDebit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->initialDebit->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->initialDebit->account_title_name ?? '-',
                    ],
                    'initial_credit' => [
                        'id' => $additional_cost->accountTitle->initialCredit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->initialCredit->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->initialCredit->account_title_name ?? '-',
                    ],
                    'depreciation_debit' => [
                        'id' => $additional_cost->accountTitle->depreciationDebit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->depreciationDebit->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->depreciationDebit->account_title_name ?? '-',
                    ],
                    'depreciation_credit' => [
                        'id' => $additional_cost->accountTitle->depreciationCredit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->depreciationCredit->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->depreciationCredit->account_title_name ?? '-',
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
            'warehouse' => [
                'id' => $fixed_asset->warehouse->id ?? '-',
                'warehouse_name' => $fixed_asset->warehouse->warehouse_name ?? '-',
            ],
            'from_request' => $fixed_asset->from_request ?? '-',
            'can_release' => $fixed_asset->can_release ?? '-',
            'is_released' => $fixed_asset->is_released,
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
            'capex_number' => $fixed_asset->capex_number ?? '-',
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
            'unit_of_measure' => [
                'id' => $fixed_asset->uom->id ?? '-',
                'uom_code' => $fixed_asset->uom->uom_code ?? '-',
                'uom_name' => $fixed_asset->uom->uom_name ?? '-',
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
            'asset_status' =>[
                'id' => $fixed_asset->assetStatus->id ?? '-',
                'asset_status_name' =>$fixed_asset->is_released ? $fixed_asset->assetStatus->asset_status_name : 'For Releasing',
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
                'id' => $fixed_asset->company->id ?? '-',
                'company_code' => $fixed_asset->company->company_code ?? '-',
                'company_name' => $fixed_asset->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->businessUnit->id ?? '-',
                'business_unit_code' => $fixed_asset->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'unit' => [
                'id' => $fixed_asset->unit->id ?? '-',
                'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                'unit_name' => $fixed_asset->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $fixed_asset->subunit->id ?? '-',
                'subunit_code' => $fixed_asset->subunit->sub_unit_code ?? '-',
                'subunit_name' => $fixed_asset->subunit->sub_unit_name ?? '-',
            ],
            'charged_department' => [
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
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
            ],
            'initial_debit' => [
                'id' => $fixed_asset->accountTitle->initialDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialDebit->account_title_name ?? '-',
            ],
            'initial_credit' => [
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
            ],
            'depreciation_debit' => [
                'id' => $fixed_asset->accountTitle->depreciationDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationDebit->account_title_name ?? '-',
            ],
            'depreciation_credit' => [
                'id' => $fixed_asset->accountTitle->depreciationCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->account_title_name ?? '-',
            ],
            'remarks' => $fixed_asset->remarks ?? '-',
            'print_count' => $fixed_asset->print_count,
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $fixed_asset->last_printed ?? '-',
            'created_at' => $fixed_asset->created_at ?? '-',
            'add_cost_sequence' => $fixed_asset->add_cost_sequence ?? null,
        ];
    }

    public function transformIndex($fixed_asset, $ymir): Collection
    {

        if ($ymir) {
            return collect($fixed_asset)->map(function ($asset) {
                return $this->ymirFixedAsset($asset);
            });
        } else {
            return collect($fixed_asset)->map(function ($asset) {
                return $this->tranformForIndex($asset);
            });
        }
    }

    public function tranformForIndex($fixed_asset)
    {
        return [
            'transfer' => $fixed_asset->transfer,
            'total_cost' => $this->calculationRepository->getTotalCost($fixed_asset->additionalCost, $fixed_asset->acquisition_cost),
            'total_adcost' => $this->calculationRepository->getTotalCost($fixed_asset->additionalCost),
            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'acquisition_date' => $fixed_asset->formula->acquisition_date ?? '-',
            'receipt' => $fixed_asset->receipt ?? '-',
            'uom' => [
                'id' => $fixed_asset->uom->id ?? '-',
                'uom_code' => $fixed_asset->uom->uom_code ?? '-',
                'uom_name' => $fixed_asset->uom->uom_name ?? '-',
            ],
            'supplier' => [
                'id' => $fixed_asset->supplier->id ?? '-',
                'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
                'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
            ],
            'quantity' => $fixed_asset->quantity ?? '-',
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->first_name ?? '-',
                'last_name' => $fixed_asset->requestor->last_name ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'warehouse_number' => [
                'id' => $fixed_asset->warehouseNumber->id ?? '-',
                'warehouse_number' => $fixed_asset->warehouseNumber->warehouse_number ?? '-',
            ],
            'warehouse' => [
                'id' => $fixed_asset->warehouse->id ?? '-',
                'warehouse_name' => $fixed_asset->warehouse->warehouse_name ?? '-',
            ],
            'capex_number' => $fixed_asset->capex_number ?? '-',
            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
            'acquisition_cost' => $fixed_asset->acquisition_cost ?? '-',
            'tag_number' => $fixed_asset->tag_number ?? '-',
            'tag_number_old' => $fixed_asset->tag_number_old ?? '-',
            'asset_description' => $fixed_asset->asset_description ?? '-',
            'asset_specification' => $fixed_asset->asset_specification ?? '-',
            'accountability' => $fixed_asset->accountability ?? '-',
            'accountable' => $fixed_asset->accountable ?? '-',
            'type_of_request' => [
                'id' => $fixed_asset->typeOfRequest->id ?? '-',
                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name ?? '-',
            ],
            'company' => [
                'id' => $fixed_asset->company->id ?? '-',
                'company_code' => $fixed_asset->company->company_code ?? '-',
                'company_name' => $fixed_asset->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->businessUnit->id ?? '-',
                'business_unit_code' => $fixed_asset->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'unit' => [
                'id' => $fixed_asset->unit->id ?? '-',
                'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                'unit_name' => $fixed_asset->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $fixed_asset->subunit->id ?? '-',
                'subunit_code' => $fixed_asset->subunit->sub_unit_code ?? '-',
                'subunit_name' => $fixed_asset->subunit->sub_unit_name ?? '-',
            ],
            'charged_department' => [
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
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
            ],
            'initial_debit' => [
                'id' => $fixed_asset->accountTitle->initialDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialDebit->account_title_name ?? '-',
            ],
            'initial_credit' => [
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
            ],
            'depreciation_debit' => [
                'id' => $fixed_asset->accountTitle->depreciationDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationDebit->account_title_name ?? '-',
            ],
            'depreciation_credit' => [
                'id' => $fixed_asset->accountTitle->depreciationCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->account_title_name ?? '-',
            ],
            'created_at' => $fixed_asset->created_at,
        ];
    }

    public function ymirFixedAsset($fixed_asset)
    {
        return [
            'id' => $fixed_asset->id,
            'acquisition_date' => $fixed_asset->formula->acquisition_date ?? '-',
            'receipt' => $fixed_asset->receipt ?? '-',
            'uom' => [
                'id' => $fixed_asset->uom->id ?? '-',
                'sync_id' => $fixed_asset->uom->sync_id ?? '-',
                'uom_code' => $fixed_asset->uom->uom_code ?? '-',
                'uom_name' => $fixed_asset->uom->uom_name ?? '-',
            ],
            'supplier' => [
                'id' => $fixed_asset->supplier->id ?? '-',
                'sync_id' => $fixed_asset->supplier->sync_id ?? '-',
                'supplier_code' => $fixed_asset->supplier->supplier_code ?? '-',
                'supplier_name' => $fixed_asset->supplier->supplier_name ?? '-',
            ],
            'quantity' => $fixed_asset->quantity ?? '-',
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->first_name ?? '-',
                'last_name' => $fixed_asset->requestor->last_name ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'warehouse_number' => [
                'id' => $fixed_asset->warehouseNumber->id ?? '-',
                'warehouse_number' => $fixed_asset->warehouseNumber->warehouse_number ?? '-',
            ],
            'warehouse' => [
                'id' => $fixed_asset->warehouse->id ?? '-',
                'warehouse_name' => $fixed_asset->warehouse->warehouse_name ?? '-',
            ],
            'capex_number' => $fixed_asset->capex_number ?? '-',
            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
            'acquisition_cost' => $fixed_asset->acquisition_cost ?? '-',
            'tag_number' => $fixed_asset->tag_number ?? '-',
            'tag_number_old' => $fixed_asset->tag_number_old ?? '-',
            'asset_description' => $fixed_asset->asset_description ?? '-',
            'asset_specification' => $fixed_asset->asset_specification ?? '-',
            'accountability' => $fixed_asset->accountability ?? '-',
            'accountable' => $fixed_asset->accountable ?? '-',
            'type_of_request' => [
                'id' => $fixed_asset->typeOfRequest->id ?? '-',
                'type_of_request_name' => $fixed_asset->typeOfRequest->type_of_request_name ?? '-',
            ],
            'company' => [
                'id' => $fixed_asset->company->id ?? '-',
                'sync_id' => $fixed_asset->company->sync_id ?? '-',
                'company_code' => $fixed_asset->company->company_code ?? '-',
                'company_name' => $fixed_asset->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->businessUnit->id ?? '-',
                'sync_id' => $fixed_asset->businessUnit->sync_id ?? '-',
                'business_unit_code' => $fixed_asset->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department->id ?? '-',
                'sync_id' => $fixed_asset->department->sync_id ?? '-',
                'department_code' => $fixed_asset->department->department_code ?? '-',
                'department_name' => $fixed_asset->department->department_name ?? '-',
            ],
            'unit' => [
                'id' => $fixed_asset->unit->id ?? '-',
                'sync_id' => $fixed_asset->unit->sync_id ?? '-',
                'unit_code' => $fixed_asset->unit->unit_code ?? '-',
                'unit_name' => $fixed_asset->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $fixed_asset->subunit->id ?? '-',
                'sync_id' => $fixed_asset->subunit->sync_id ?? '-',
                'subunit_code' => $fixed_asset->subunit->sub_unit_code ?? '-',
                'subunit_name' => $fixed_asset->subunit->sub_unit_name ?? '-',
            ],
//            'charged_department' => [
//                'id' => $fixed_asset->department->id ?? '-',
//                'department_code' => $fixed_asset->department->department_code ?? '-',
//                'department_name' => $fixed_asset->department->department_name ?? '-',
//            ],
            'location' => [
                'id' => $fixed_asset->location->id ?? '-',
                'sync_id' => $fixed_asset->location->sync_id ?? '-',
                'location_code' => $fixed_asset->location->location_code ?? '-',
                'location_name' => $fixed_asset->location->location_name ?? '-',
            ],
//            'account_title' => [
//                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
//                'sync_id'=> $fixed_asset->accountTitle->initialCredit->sync_id ?? '-',
//                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
//                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
//            ],
            'initial_debit' => [
                'id' => $fixed_asset->accountTitle->initialDebit->id ?? '-',
                'sync_id' => $fixed_asset->accountTitle->initialDebit->sync_id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialDebit->account_title_name ?? '-',
            ],
            'initial_credit' => [
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'sync_id' => $fixed_asset->accountTitle->initialCredit->sync_id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->account_title_name ?? '-',
            ],
            'depreciation_debit' => [
                'id' => $fixed_asset->accountTitle->depreciationDebit->id ?? '-',
                'sync_id' => $fixed_asset->accountTitle->depreciationDebit->sync_id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationDebit->account_title_name ?? '-',
            ],
            'depreciation_credit' => [
                'id' => $fixed_asset->accountTitle->depreciationCredit->id ?? '-',
                'sync_id' => $fixed_asset->accountTitle->depreciationCredit->sync_id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->account_title_name ?? '-',
            ],
            'created_at' => $fixed_asset->created_at,
        ];
    }


    public function faIndex($ymir, $movement = null): JsonResponse
    {
        $fixed_assets = FixedAsset::select([
            'id', 'vladimir_tag_number', 'tag_number', 'tag_number_old', 'asset_description', 'receipt', 'acquisition_cost',
            'quantity', 'accountability', 'accountable', 'asset_specification',
            'from_request', 'is_released', 'formula_id', 'requester_id', 'uom_id',
            'warehouse_number_id', 'capex_id', 'sub_capex_id', 'type_of_request_id', 'supplier_id',
            'department_id', 'major_category_id', 'minor_category_id', 'asset_status_id',
            'cycle_count_status_id', 'depreciation_status_id', 'movement_status_id',
            'location_id', 'account_id', 'company_id', 'business_unit_id', 'unit_id', 'subunit_id', 'created_at'
        ])
            ->with([
                'formula',
                'additionalCost',
                'requestor',
                'warehouseNumber:id,warehouse_number',
                'capex',
                'subCapex',
                'typeOfRequest:id,type_of_request_name',
                'supplier:id,supplier_code,supplier_name',
                'department.division:id,division_name',
                'majorCategory:id,major_category_name',
                'minorCategory:id,minor_category_name',
                'assetStatus:id,asset_status_name',
                'cycleCountStatus:id,cycle_count_status_name',
                'depreciationStatus:id,depreciation_status_name',
                'movementStatus:id,movement_status_name',
                'company:id,company_name,company_code',
                'businessUnit:id,business_unit_name,business_unit_code',
                'department:id,department_name,department_code',
                'unit:id,unit_name,unit_code',
                'subunit:id,sub_unit_name,sub_unit_code',
                'location:id,location_name,location_code',
            ])
            ->when($movement != null, function ($query) {
                $query->where('from_request', '!=', 1)
                    ->orWhere(function ($query) {
                        $query->where('from_request', 1)
                            ->where('is_released', 1);
                    });
            })->when($ymir == true, function ($query) {
                $query->where('from_request', 1)
                    ->whereNotNull('depreciation_method')
                    ->where('is_released', 1);
            })
//            ->where(function ($query) {
//                $query->where('from_request', '!=', 1)
//                    ->orWhere(function ($query) {
//                        $query->where('from_request', 1)
//                            ->where('is_released', 1);
//                    });
//            })
            ->get();

        $fixed_assets = $fixed_assets->map(function ($fixedAsset) {
            $fixedAsset->transfer = $fixedAsset->isStillInTransferApproval() ? 1 : 0;
            return $fixedAsset;
        });

        return response()->json([
            'message' => 'Fixed Assets retrieved successfully.',
            'data' => $this->transformIndex($fixed_assets, $ymir)
        ], 200);
    }

    private function faWithVoucherView($page, $perPage)
    {
        $fixedAssets = FixedAsset::select($this->fixedAssetFields())
            ->whereNotNull('po_number')
            ->where('po_number', '!=', '-')
            ->whereNotNull('receipt')
            ->where('receipt', '!=', '-')
            ->where(function ($query) {
                $query->whereNull('voucher')
                    ->orWhere('voucher', '=', '-');
            })
            ->get();

        $additionalCosts = AdditionalCost::select($this->additionalCostFields())
            ->join('fixed_assets', 'fixed_assets.id', '=', 'additional_costs.fixed_asset_id')
            ->whereNotNull('additional_costs.po_number')
            ->where('additional_costs.po_number', '!=', '-')
            ->whereNotNull('additional_costs.receipt')
            ->where('additional_costs.receipt', '!=', '-')
            ->where(function ($query) {
                $query->whereNull('additional_costs.voucher')
                    ->orWhere('additional_costs.voucher', '=', '-');
            })
            ->get();

        $fixedAssets = $fixedAssets->filter(function ($fixedAsset) {
            $voucher = $this->getVoucher($fixedAsset->receipt, $fixedAsset->po_number);
            if ($voucher) {
                $fixedAsset->voucher = $voucher['voucher_no'];
                $fixedAsset->voucher_date = $voucher['voucher_date'];
                return true;
            }
            return false;
        });

        $additionalCosts = $additionalCosts->filter(function ($additionalCost) {
            $voucher = $this->getVoucher($additionalCost->receipt, $additionalCost->po_number);
            if ($voucher) {
                $additionalCost->voucher = $voucher['voucher_no'];
                $additionalCost->voucher_date = $voucher['voucher_date'];
                return true;
            }
            return false;
        });

        $combinedResults = $fixedAssets->merge($additionalCosts);

        $paginatedResults = $this->paginateResults($combinedResults, $page, $perPage);

        $paginatedResults->setCollection($paginatedResults->getCollection()->map(function ($item) {
            return $this->transformSearchFixedAsset($item);
        }));

        return $paginatedResults;
    }

    public function getVoucher($receipt, $po_number)
    {
        $poFromRequest = $po_number;
        $rrFromRequest = $receipt;
        $poBatches = PoBatch::with('fistoTransaction')->where('po_no', "PO#" . $poFromRequest)->orderBy('request_id')->get();

        $poBatch = $poBatches->first(function ($poBatch) use ($rrFromRequest) {
            $rr_group = json_decode($poBatch->rr_group);
            return in_array($rrFromRequest, $rr_group);
        });

        if ($poBatch) {
            if ($poBatch->fistoTransaction->voucher_no == null || $poBatch->fistoTransaction->voucher_month == null) {
                return null;
            }
            return [
                'voucher_no' => $poBatch->fistoTransaction->voucher_no,
                'voucher_date' => $poBatch->fistoTransaction->voucher_month
            ];
        } else {
            return null;
        }
    }

    private function fixedAssetFields(): array
    {
        return [
            'id',
            'requester_id',
            'pr_number',
            'po_number',
            'rr_number',
            'warehouse_id',
            'warehouse_number_id',
            'capex_id',
            'sub_capex_id',
            'transaction_number',
            'reference_number',
            'vladimir_tag_number',
            'tag_number',
            'tag_number_old',
            'from_request',
            'can_release',
            'is_released',
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
            'unit_id',
            'subunit_id',
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
    }

    private function additionalCostFields(): array
    {
        return [
            'additional_costs.id',
            'additional_costs.requester_id',
            'additional_costs.pr_number',
            'additional_costs.po_number',
            'additional_costs.rr_number',
            'additional_costs.warehouse_id',
            'additional_costs.warehouse_number_id',
            'fixed_assets.capex_id AS capex_id',
            'fixed_assets.sub_capex_id AS sub_capex_id',
            'additional_costs.transaction_number',
            'additional_costs.reference_number',
            'fixed_assets.vladimir_tag_number AS vladimir_tag_number',
            'fixed_assets.tag_number AS tag_number',
            'fixed_assets.tag_number_old AS tag_number_old',
            'additional_costs.from_request',
            'additional_costs.can_release',
            'additional_costs.is_released',
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
            'additional_costs.unit_id',
            'additional_costs.subunit_id',
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
    }
}
