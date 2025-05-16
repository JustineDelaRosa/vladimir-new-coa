<?php

namespace App\Repositories;

use App\Models\AccountingEntries;
use App\Models\AssetApproval;
use App\Models\AssetTransferRequest;
use App\Models\DepreciationHistory;
use App\Models\MinorCategory;
use App\Models\PoBatch;
use App\Models\TypeOfRequest;
use App\Models\YmirPRTransaction;
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
        $fixedAsset = $formula->fixedAsset()->create($fixedAssetData);
        $accountingEntries = $fixedAsset->accountingEntries()->create([
            'initial_debit' => $request['initial_debit_id'],
            'initial_credit' => $request['initial_credit_id'],
            'depreciation_debit' => $request['depreciation_debit_id'],
            'depreciation_credit' => $request['depreciation_credit_id'],
        ]);
        $fixedAsset->update(['account_id' => $accountingEntries->id]);

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
                    $depreciationMethod === 'STL' ? $depreciationMethod : ucwords(strtolower($depreciationMethod))
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
//        $accountingEntry = MinorCategory::where('id', $request['minor_category_id'])->first()->accounting_entries_id;
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
//            'account_id' => $accountingEntry,
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
//        $accountingEntry = MinorCategory::where('id', $request['minor_category_id'])->first()->accounting_entries_id;
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
//            'account_id' => $accountingEntry,
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
//            'start_depreciation' => isset($request['release_date']) && $majorCategory->est_useful_life != 0.0
////                ? $this->calculationRepository->getStartDepreciation($request['voucher_date'])
//                ? $this->calculationRepository->getStartDepreciation($request['depreciation_method'], $request['release_date'])
//                : null
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

        $runningDepreciation = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;
        $fullyDepreciated = DepreciationStatus::where('depreciation_status_name', 'Fully Depreciated')->first()->id;

        $firstQuery = ($status === 'deactivated')
            ? FixedAsset::onlyTrashed()->select($this->fixedAssetFields())
            : FixedAsset::select($this->fixedAssetFields());

        $secondQuery = ($status === 'deactivated')
            ? AdditionalCost::onlyTrashed()->select($this->additionalCostFields())->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            : AdditionalCost::select($this->additionalCostFields())->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id');

        $smallToolsId = TypeOfRequest::where('type_of_request_name', 'Small Tools')->first()->id;
        $conditions = [
            'To Depreciate' => ['depreciation_method' => null, 'is_released' => 1, 'is_additional_cost' => 0],
            'Fixed Asset' => ['is_additional_cost' => 0],
            'Additional Cost' => ['is_additional_cost' => 1],
            'From Request' => ['from_request' => 1],
            'Small Tools' => ['type_of_request_id' => $smallToolsId],
            'Running Depreciation' => ['depreciation_status_id' => $runningDepreciation, 'is_additional_cost' => 0],
            'Fully Depreciated' => ['depreciation_status_id' => $fullyDepreciated, 'is_additional_cost' => 0],
        ];

        // Apply filters first
        if (!empty($filter)) {
            $this->applyFilters($firstQuery, $filter, $conditions);
            $this->applyFilters($secondQuery, $filter, $conditions, 'additional_costs');
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
            'accountTitle.initialCredit' => ['credit_name'],
        ];
        // Then apply search within those filters
        if (!empty($search)) {
            $mainAttributesFixedAsset = [
                'vladimir_tag_number', 'tag_number', 'tag_number_old',
                'asset_description', 'accountability', 'accountable',
                'brand', 'depreciation_method', 'transaction_number',
                'reference_number', 'po_number', 'rr_number', 'ymir_pr_number',
            ];

            $mainAttributesAdditionalCost = [
                'vladimir_tag_number', 'tag_number', 'tag_number_old',
                'additional_costs.po_number', 'additional_costs.rr_number',
            ];



            // Special case for "To Depreciate" filter
            if (count($filter) == 1 && $filter[0] == 'To Depreciate') {
                // Skip additional costs query entirely
                $secondQuery->where('additional_costs.id', 0);

                // Search only within the filtered fixed assets
                $firstQuery->where(function ($query) use ($mainAttributesFixedAsset, $search, $relationAttributes) {
                    // Search in main attributes
                    foreach ($mainAttributesFixedAsset as $attribute) {
                        $query->orWhere($attribute, 'like', '%' . $search . '%');
                    }

                    // Search in relation attributes
                    foreach ($relationAttributes as $relation => $attributes) {
                        foreach ($attributes as $attribute) {
                            $query->orWhereHas($relation, function ($whereQuery) use ($attribute, $search) {
                                $whereQuery->where($attribute, 'like', '%' . $search . '%');
                            });
                        }
                    }
                });
            } else {


                // Apply search for fixed assets
                $firstQuery->where(function ($query) use ($mainAttributesFixedAsset, $relationAttributes, $search) {
                    // Search in main attributes
                    foreach ($mainAttributesFixedAsset as $attribute) {
                        $query->orWhere($attribute, 'like', '%' . $search . '%');
                    }

                    // Search in relation attributes
                    foreach ($relationAttributes as $relation => $attributes) {
                        foreach ($attributes as $attribute) {
                            $query->orWhereHas($relation, function ($whereQuery) use ($attribute, $search) {
                                $whereQuery->where($attribute, 'like', '%' . $search . '%');
                            });
                        }
                    }
                });

                // Apply search for additional costs
                $secondQuery->where(function ($query) use ($mainAttributesAdditionalCost, $relationAttributes, $search) {
                    // Search in main attributes
                    foreach ($mainAttributesAdditionalCost as $attribute) {
                        $query->orWhere($attribute, 'like', '%' . $search . '%');
                    }

                    // Search in relation attributes
                    foreach ($relationAttributes as $relation => $attributes) {
                        // Skip subCapex for additional costs
                        if ($relation !== 'subCapex') {
                            foreach ($attributes as $attribute) {
                                $query->orWhereHas($relation, function ($whereQuery) use ($attribute, $search) {
                                    $whereQuery->where($attribute, 'like', '%' . $search . '%');
                                });
                            }
                        }
                    }
                });
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


    //todo: this is the old search function
/*    public function searchFixedAsset($search, $status, $page, $per_page = null, $filter = null)
    {
        $filter = $filter ? array_map('trim', explode(',', $filter)) : [];
        //check if filter only contains 'With Voucher'
//        if (count($filter) == 1 && $filter[0] == 'With Voucher') {
//            return $this->faWithVoucherView($page, $per_page);
//        }

        $runningDepreciation = DepreciationStatus::where('depreciation_status_name', 'Running Depreciation')->first()->id;

        $firstQuery = ($status === 'deactivated')
            ? FixedAsset::onlyTrashed()->select($this->fixedAssetFields())
            : FixedAsset::select($this->fixedAssetFields());

        $secondQuery = ($status === 'deactivated')
            ? AdditionalCost::onlyTrashed()->select($this->additionalCostFields())->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            : AdditionalCost::select($this->additionalCostFields())->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id');

        $smallToolsId = TypeOfRequest::where('type_of_request_name', 'Small Tools')->first()->id;
        $conditions = [
            'To Depreciate' => ['depreciation_method' => null, 'is_released' => 1, 'is_additional_cost' => 0],
            'Fixed Asset' => ['is_additional_cost' => 0],
            'Additional Cost' => ['is_additional_cost' => 1],
            'From Request' => ['from_request' => 1],
            'Small Tools' => ['type_of_request_id' => $smallToolsId],
            'Running Depreciation' => ['depreciation_status_id' => $runningDepreciation, 'is_additional_cost' => 0],
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
                'po_number',
                'rr_number',
                'ymir_pr_number',
            ];

            $mainAttributesAdditionalCost = [
                'vladimir_tag_number',
                'tag_number',
                'tag_number_old',
                'additional_costs.po_number',
                'additional_costs.rr_number',
            ];

// In your searchFixedAsset method, update both query handlings:

            if (count($filter) == 1 && $filter[0] == 'To Depreciate') {
                // For fixed assets query
                $firstQuery->where(function ($query) use ($mainAttributesFixedAsset, $search) {
                    // Apply "To Depreciate" conditions first
                    $query->whereNull('depreciation_method')
                        ->where('is_released', 1)
                        ->where('is_additional_cost', 0)
                        ->where('from_request', 0);

                    // Then apply search as a nested condition
                    $query->where(function ($subQuery) use ($mainAttributesFixedAsset, $search) {
                        foreach ($mainAttributesFixedAsset as $attribute) {
                            $subQuery->orWhere($attribute, 'like', '%' . $search . '%');
                        }
                    });
                });

                // For additional costs query - prevent it from returning any results when "To Depreciate" is the only filter
                $secondQuery->where('additional_costs.id', 0); // Force no results from additional costs
            } else {
                // Original behavior for other filter combinations
                foreach ($mainAttributesFixedAsset as $attribute) {
                    $firstQuery->orWhere($attribute, 'like', '%' . $search . '%');
                }

                foreach ($mainAttributesAdditionalCost as $attribute) {
                    $secondQuery->orWhere($attribute, 'like', '%' . $search . '%');
                }
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
                'accountTitle.initialCredit' => ['credit_name'],
            ];

// Check if filter only contains 'To Depreciate'
            if (count($filter) == 1 && $filter[0] == 'To Depreciate') {
                // For first query (fixed assets), apply "To Depreciate" conditions and then search within those results
                $firstQuery->where(function ($query) use ($relationAttributes, $search) {
                    // Apply "To Depreciate" conditions
                    $query->whereNull('depreciation_method')
                        ->where('is_released', 1)
                        ->where('is_additional_cost', 0)
                        ->where('from_request', 0);

                    // Then apply relational search as a nested condition
                    $query->where(function ($subQuery) use ($relationAttributes, $search) {
                        foreach ($relationAttributes as $relation => $attributes) {
                            foreach ($attributes as $attribute) {
                                $subQuery->orWhereHas($relation, function ($whereQuery) use ($attribute, $search) {
                                    $whereQuery->where($attribute, 'like', '%' . $search . '%');
                                });
                            }
                        }
                    });
                });

                // Skip second query (additional costs) entirely for "To Depreciate" filter
                // It's already being excluded earlier  in the code6
            } else {
                // Original behavior for other filter combinations
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
        }


        $results = $firstQuery->unionAll($secondQuery)->orderBy('asset_description')->get();

        $results = $this->paginateResults($results, $page, $per_page);

        $results->setCollection($results->getCollection()->values());
        $results->getCollection()->transform(function ($item) {
            return $this->transformSearchFixedAsset($item);
        });
        return $results;
    }*/

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
//        try {
//            $YmirPRNumber = YmirPRTransaction::where('pr_number', $fixed_asset->pr_number)->first()->pr_year_number_id ?? null;
//        } catch (\Exception $e) {
//            $YmirPRNumber = $fixed_asset->pr_number;
//        }
        $fixed_asset->additional_cost_count = $fixed_asset->additionalCostWithTrashed->where('is_released', 1) ? $fixed_asset->additionalCostWithTrashed->where('is_released', 1)->count() : 0;
//        $smallToolsId = TypeOfRequest::where('type_of_request_name', 'Small Tools')->first()->id;
//        $isSmallTools = $smallToolsId == $fixed_asset->type_of_request_id;

        return [
            'total_cost' => $this->calculationRepository->getTotalCost($fixed_asset->additionalCostWithTrashed->where('is_released', 1), $fixed_asset->acquisition_cost),
            'total_adcost' => $this->calculationRepository->getTotalCost($fixed_asset->additionalCostWithTrashed->where('is_released', 1)),
            'can_add' => $fixed_asset->is_released ? 1 : 0,
            'can_update' => $fixed_asset->accountingEntries->depreciationDebit ? 0 : 1,  //DepreciationHistory::where('fixed_asset_id', $fixed_asset->id)->exists() ? 0 : 1,
            'is_depreciated' => $fixed_asset->accountingEntries->depreciationDebit ? 1 : 0,
            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'transaction_number' => $fixed_asset->transaction_number,
            'reference_number' => $fixed_asset->reference_number,
            /*            'small_tools_item' => $fixed_asset->assetSmallTools ?
                            $fixed_asset->assetSmallTools->groupBy(function ($smallTool) {
                                return $smallTool->item->sync_id . '-' . $smallTool->item->item_code . '-' . $smallTool->item->item_name . '-' . ($smallTool->to_release ? 'For Releasing' : $smallTool->status_description);
                            })->map(function ($group) {
                                $firstItem = $group->first();
                                return [
                                    'id' => $firstItem->item->id ?? '-',
                                    'fixed_asset_id' => $firstItem->fixed_asset_id ?? '-',
                                    'sync_id' => $firstItem->item->sync_id ?? '-',
                                    'item_code' => $firstItem->item->item_code ?? '-',
                                    'item_name' => $firstItem->item->item_name ?? '-',
                                    'quantity' => $group->sum('quantity') ?? '-',
                                    'status' => $firstItem->is_active ?? '-',
                                    'status_description' => $firstItem->to_release ? 'For Releasing' : $firstItem->status_description,
                                ];
                            })->values()->all()
                            : [],*/
            'small_tools' => $fixed_asset->assetSmallTools()->withTrashed()->get() ?
                $fixed_asset->assetSmallTools()->withTrashed()->get()->map(function ($smallTool) {
                    return [
                        'id' => $smallTool->id ?? '-',
                        'description' => $smallTool->description ?? '-',
                        'specification' => $smallTool->specification ?? '-',
                        'pr_number' => $smallTool->pr_number ?? '-',
                        'po_number' => $smallTool->po_number ?? '-',
                        'rr_number' => $smallTool->rr_number ?? '-',
                        'acquisition_cost' => $smallTool->acquisition_cost ?? '-',
                        'quantity' => $smallTool->quantity ?? '-',
                        'status' => $smallTool->is_active ?? '-',
                        'status_description' => $smallTool->status_description,
                    ];
                }) : [],
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'first_name' => $fixed_asset->requestor->first_name ?? '-',
                'last_name' => $fixed_asset->requestor->last_name ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'is_released' => $fixed_asset->is_released,
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'ymir_pr_number' => $fixed_asset->ymir_pr_number,  //$YmirPRNumber ?? $fixed_asset->pr_number,
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
            'inclusion' => $fixed_asset->inclusion ?? [],
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
            'asset_status' => [
                'id' => $fixed_asset->assetStatus->id ?? '-',
                'asset_status_name' => $fixed_asset->from_request ? ($fixed_asset->is_released ? $fixed_asset->assetStatus->asset_status_name : 'For Releasing') : $fixed_asset->assetStatus->asset_status_name ?? '-',
            ],
            'cycle_count_status' => [
                'id' => $fixed_asset->cycleCountStatus->id ?? '-',
                'cycle_count_status_name' => $fixed_asset->cycleCountStatus->cycle_count_status_name ?? '-',
            ],
            'depreciation_status' => $fixed_asset->depreciationStatus ? [
                'id' => $fixed_asset->depreciationStatus->id ?? '-',
                'depreciation_status_name' => $fixed_asset->additionalCost->contains(function ($additionalCost) {
                    return $additionalCost->depreciationStatus && $additionalCost->depreciationStatus->depreciation_status_name == 'Running Depreciation';
                }) ? 'Running Depreciation' : $fixed_asset->depreciationStatus->depreciation_status_name ?? '-',
            ] : '-',
            'movement_status' => [
                'id' => $fixed_asset->movementStatus->id ?? '-',
                'movement_status_name' => $fixed_asset->movementStatus->movement_status_name ?? '-',
            ],
            'is_additional_cost' => $fixed_asset->is_additional_cost ?? '-',
            'is_printable' => $fixed_asset->is_printable,
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
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->credit_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->credit_name ?? '-',
            ],
            'initial_debit' => [
                'id' => $fixed_asset->accountTitle->initialDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialDebit->account_title_name ?? '-',
                'depreciation_debit' => $fixed_asset->accountTitle->initialDebit->depreciationDebit ?? '-',
            ],
            'initial_credit' => [
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->credit_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->credit_name ?? '-',
            ],
            'depreciation_debit' => [
                'id' => $fixed_asset->accountTitle->depreciationDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationDebit->account_title_name ?? '-',
            ],
            'depreciation_credit' => [
                'id' => $fixed_asset->accountTitle->depreciationCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->credit_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->credit_name ?? '-',
            ],
            'remarks' => $fixed_asset->remarks,
            'print_count' => $fixed_asset->print_count,
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $fixed_asset->last_printed,
            'tagging' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'created_at' => $fixed_asset->created_at,
            //->where('is_released', 1)
            'additional_cost' => isset($fixed_asset->additionalCostWithTrashed) ? $fixed_asset->additionalCostWithTrashed->map(function ($additional_cost) {
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
                        'asset_status_name' => $additional_cost->from_request
                            ? ($additional_cost->is_released
                                ? ($additional_cost->deleted_at === null
                                    ? $additional_cost->assetStatus->asset_status_name
                                    : 'Replaced')
                                : 'For Releasing')
                            : $additional_cost->assetStatus->asset_status_name ?? '-',
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
                        'account_title_code' => $additional_cost->accountTitle->initialCredit->credit_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->initialCredit->credit_name ?? '-',
                    ],
                    'initial_debit' => [
                        'id' => $additional_cost->accountTitle->initialDebit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->initialDebit->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->initialDebit->account_title_name ?? '-',
                    ],
                    'initial_credit' => [
                        'id' => $additional_cost->accountTitle->initialCredit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->initialCredit->credit_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->initialCredit->credit_name ?? '-',
                    ],
                    'depreciation_debit' => [
                        'id' => $additional_cost->accountTitle->depreciationDebit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->depreciationDebit->account_title_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->depreciationDebit->account_title_name ?? '-',
                    ],
                    'depreciation_credit' => [
                        'id' => $additional_cost->accountTitle->depreciationCredit->id ?? '-',
                        'account_title_code' => $additional_cost->accountTitle->depreciationCredit->credit_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->depreciationCredit->credit_name ?? '-',
                    ],
                    'remarks' => $additional_cost->remarks ?? '-',
                ];

            })->values() : [],
        ];
    }

    public function transformSearchFixedAsset($fixed_asset): array
    {
        $fixed_asset->additional_cost_count = $fixed_asset->additionalCostWithTrashed ? count($fixed_asset->additionalCostWithTrashed) : 0;
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
                'id' => $fixed_asset->capex_id ?? '-',
                'capex' => $fixed_asset->capex_number ?? '-',
                'project_name' => $fixed_asset->capex_project_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $fixed_asset->sub_capex_id ?? '-',
                'sub_capex' => $fixed_asset->sub_capex_number ?? '-',
                'sub_project' => $fixed_asset->sub_capex_project_name ?? '-',
            ],
            'capex_number' => $fixed_asset->capex_number ?? '-',
            'vladimir_tag_number' => $fixed_asset->vladimir_tag_number,
            'tag_number' => $fixed_asset->tag_number ?? '-',
            'tag_number_old' => $fixed_asset->tag_number_old ?? '-',
            'asset_description' => $fixed_asset->asset_description ?? '-',
            'type_of_request' => [
                'id' => $fixed_asset->type_of_request_id ?? '-',
                'type_of_request_name' => $fixed_asset->type_of_request_name ?? '-',
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
            'is_printable' => $fixed_asset->is_printable ?? 0,
            'status' => $fixed_asset->is_active ?? '-',
            'quantity' => $fixed_asset->quantity ?? '-',
            'depreciation_method' => $fixed_asset->depreciation_method ?? '-',
            //                    'salvage_value' => $fixed_asset->salvage_value,
            'acquisition_date' => $fixed_asset->acquisition_date ?? '-',
            'acquisition_cost' => $fixed_asset->acquisition_cost ?? '-',
            'asset_status' => [
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
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->credit_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->credit_name ?? '-',
            ],
            'initial_debit' => [
                'id' => $fixed_asset->accountTitle->initialDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialDebit->account_title_name ?? '-',
                'depreciation_debit' => $fixed_asset->accountTitle->initialDebit->depreciationDebit ?? '-',
            ],
            'initial_credit' => [
                'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->initialCredit->credit_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->initialCredit->credit_name ?? '-',
            ],
            'depreciation_debit' => [
                'id' => $fixed_asset->accountTitle->depreciationDebit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationDebit->account_title_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationDebit->account_title_name ?? '-',
            ],
            'depreciation_credit' => [
                'id' => $fixed_asset->accountTitle->depreciationCredit->id ?? '-',
                'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->credit_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->credit_name ?? '-',
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
//            'transfer' => $fixed_asset->transfer,
//            'total_cost' => $this->calculationRepository->getTotalCost($fixed_asset->additionalCost, $fixed_asset->acquisition_cost),
//            'total_adcost' => $this->calculationRepository->getTotalCost($fixed_asset->additionalCost),

            'is_printable' => $fixed_asset->is_printable,
            'total_cost' => $fixed_asset->total_additional_cost + $fixed_asset->acquisition_cost,
            'total_adcost' => $fixed_asset->total_additional_cost,
            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'remaining_book_value' => $fixed_asset->remaining_book_value ?? $fixed_asset->depreciable_basis,
            'id' => $fixed_asset->id,
            'acquisition_date' => $fixed_asset->acquisition_date ?? '-',
            'receipt' => $fixed_asset->receipt ?? '-',
            'uom' => [
                'id' => $fixed_asset->uom_id ?? '-',
                'sync_id' => $fixed_asset->uom_sync_id ?? '-',
                'uom_code' => $fixed_asset->uom_code ?? '-',
                'uom_name' => $fixed_asset->uom_name ?? '-',
            ],
            'supplier' => [
                'id' => $fixed_asset->supplier_id ?? '-',
                'sync_id' => $fixed_asset->supplier_sync_id ?? '-',
                'supplier_code' => $fixed_asset->supplier_code ?? '-',
                'supplier_name' => $fixed_asset->supplier_name ?? '-',
            ],
            'quantity' => $fixed_asset->quantity ?? '-',
            'requestor' => [
                'id' => $fixed_asset->requestor_id ?? '-',
                'username' => $fixed_asset->requestor_username ?? '-',
                'first_name' => $fixed_asset->requestor_firstname ?? '-',
                'last_name' => $fixed_asset->requestor_lastname ?? '-',
                'employee_id' => $fixed_asset->requestor_employee_id ?? '-',
            ],
            'warehouse_number' => [
                'id' => $fixed_asset->warehouse_number_id ?? '-',
                'warehouse_number' => $fixed_asset->warehouse_number ?? '-',
            ],
            'warehouse' => [
                'id' => $fixed_asset->warehouse_id ?? '-',
                'warehouse_name' => $fixed_asset->warehouse_name ?? '-',
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
            /*            'small_tools_item' => $fixed_asset->assetSmallTools ?
                            $fixed_asset->assetSmallTools->map(function ($smallTool) {
                                return [
                                    'id' => $smallTool->id ?? '-',
                                    'description' => $smallTool->description ?? '-',
                                    'quantity' => $smallTool->quantity ?? '-',
                                    'status' => $smallTool->is_active ?? '-',
                                    'status_description' => $smallTool->status_description,
            //                        'items' => $smallTool->item ?? [],
                                ];
                            })
                            : [],*/
//            'small_tools' => $fixed_asset->assetSmallTools ?
//                $fixed_asset->assetSmallTools->where('status_description', 'Good')->map(function ($smallTool) {
//                    return [
//                        'id' => $smallTool->id ?? '-',
//                        'description' => $smallTool->description ?? '-',
//                        'specification' => $smallTool->specification ?? '-',
//                        'pr_number' => $smallTool->pr_number ?? '-',
//                        'po_number' => $smallTool->po_number ?? '-',
//                        'rr_number' => $smallTool->rr_number ?? '-',
//                        'acquisition_cost' => $smallTool->acquisition_cost ?? '-',
//                        'quantity' => $smallTool->quantity ?? '-',
//                        'status' => $smallTool->is_active ?? '-',
//                        'status_description' => $smallTool->status_description,
//                    ];
//                })->values()
//                : [],

            'small_tools' => $fixed_asset->small_tools ?? [],
            'type_of_request' => [
                'id' => $fixed_asset->type_of_request_id ?? '-',
                'type_of_request_name' => $fixed_asset->type_of_request_name ?? '-',
            ],
            'company' => [
                'id' => $fixed_asset->company_id ?? '-',
                'company_code' => $fixed_asset->company_code ?? '-',
                'company_name' => $fixed_asset->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->business_unit_id ?? '-',
                'business_unit_code' => $fixed_asset->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department_id ?? '-',
                'department_code' => $fixed_asset->department_code ?? '-',
                'department_name' => $fixed_asset->department_name ?? '-',
            ],
            'unit' => [
                'id' => $fixed_asset->unit_id ?? '-',
                'unit_code' => $fixed_asset->unit_code ?? '-',
                'unit_name' => $fixed_asset->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $fixed_asset->subunit_id ?? '-',
                'subunit_code' => $fixed_asset->sub_unit_code ?? '-',
                'subunit_name' => $fixed_asset->sub_unit_name ?? '-',
            ],
            'charged_department' => [
                'id' => $fixed_asset->department_id ?? '-',
                'department_code' => $fixed_asset->department_code ?? '-',
                'department_name' => $fixed_asset->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->location_id ?? '-',
                'location_code' => $fixed_asset->location_code ?? '-',
                'location_name' => $fixed_asset->location_name ?? '-',
            ],
            /*            'account_title' => [
                            'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                            'account_title_code' => $fixed_asset->accountTitle->initialCredit->credit_code ?? '-',
                            'account_title_name' => $fixed_asset->accountTitle->initialCredit->credit_name ?? '-',
                        ],*/
            'initial_debit' => [
                'id' => $fixed_asset->initial_debit_id ?? '-',
                'account_title_code' => $fixed_asset->initial_debit_account_title_code ?? '-',
                'account_title_name' => $fixed_asset->initial_debit_account_title_name ?? '-',
                'depreciation_debit' => $fixed_asset->depreciation_debits ?? [],
            ],
            'initial_credit' => [
                'id' => $fixed_asset->initial_credit_id ?? '-',
                'account_title_code' => $fixed_asset->initial_credit_account_title_code ?? '-',
                'account_title_name' => $fixed_asset->initial_credit_account_title_name ?? '-',
            ],
            'depreciation_debit' => [
                'id' => $fixed_asset->depreciation_debit_id ?? '-',
                'account_title_code' => $fixed_asset->depreciation_debit_account_title_code ?? '-',
                'account_title_name' => $fixed_asset->depreciation_debit_account_title_name ?? '-',
            ],
            'depreciation_credit' => [
                'id' => $fixed_asset->depreciation_credit_id ?? '-',
                'account_title_code' => $fixed_asset->depreciation_credit_account_title_code ?? '-',
                'account_title_name' => $fixed_asset->depreciation_credit_account_title_name ?? '-',
            ],
            'created_at' => $fixed_asset->created_at,
        ];
    }

    public function ymirFixedAsset($fixed_asset)
    {
        return [
            'id' => $fixed_asset->id,
            'acquisition_date' => $fixed_asset->acquisition_date ?? '-',
            'receipt' => $fixed_asset->receipt ?? '-',
            'uom' => [
                'id' => $fixed_asset->uom_id ?? '-',
                'sync_id' => $fixed_asset->uom_sync_id ?? '-',
                'uom_code' => $fixed_asset->uom_code ?? '-',
                'uom_name' => $fixed_asset->uom_name ?? '-',
            ],
            'supplier' => [
                'id' => $fixed_asset->supplier_id ?? '-',
                'sync_id' => $fixed_asset->supplier_sync_id ?? '-',
                'supplier_code' => $fixed_asset->supplier_code ?? '-',
                'supplier_name' => $fixed_asset->supplier_name ?? '-',
            ],
            'quantity' => $fixed_asset->quantity ?? '-',
            'requestor' => [
                'id' => $fixed_asset->requestor_id ?? '-',
                'username' => $fixed_asset->requestor_username ?? '-',
                'first_name' => $fixed_asset->requestor_firstname ?? '-',
                'last_name' => $fixed_asset->requestor_lastname ?? '-',
                'employee_id' => $fixed_asset->requestor_employee_id ?? '-',
            ],
            'warehouse_number' => [
                'id' => $fixed_asset->warehouse_number_id ?? '-',
                'warehouse_number' => $fixed_asset->warehouse_number ?? '-',
            ],
            'warehouse' => [
                'id' => $fixed_asset->warehouse_id ?? '-',
                'warehouse_name' => $fixed_asset->warehouse_name ?? '-',
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
            /*            'small_tools_item' => $fixed_asset->assetSmallTools ?
                            $fixed_asset->assetSmallTools->map(function ($smallTool) {
                                return [
                                    'id' => $smallTool->id ?? '-',
                                    'description' => $smallTool->description ?? '-',
                                    'quantity' => $smallTool->quantity ?? '-',
                                    'status' => $smallTool->is_active ?? '-',
                                    'status_description' => $smallTool->status_description,
            //                        'items' => $smallTool->item ?? [],
                                ];
                            })
                            : [],*/
//            'small_tools' => $fixed_asset->assetSmallTools ?
//                $fixed_asset->assetSmallTools->where('status_description', 'Good')->map(function ($smallTool) {
//                    return [
//                        'id' => $smallTool->id ?? '-',
//                        'description' => $smallTool->description ?? '-',
//                        'specification' => $smallTool->specification ?? '-',
//                        'pr_number' => $smallTool->pr_number ?? '-',
//                        'po_number' => $smallTool->po_number ?? '-',
//                        'rr_number' => $smallTool->rr_number ?? '-',
//                        'acquisition_cost' => $smallTool->acquisition_cost ?? '-',
//                        'quantity' => $smallTool->quantity ?? '-',
//                        'status' => $smallTool->is_active ?? '-',
//                        'status_description' => $smallTool->status_description,
//                    ];
//                })->values()
//                : [],

            'small_tools' => $fixed_asset->small_tools ?? [],
            'type_of_request' => [
                'id' => $fixed_asset->type_of_request_id ?? '-',
                'type_of_request_name' => $fixed_asset->type_of_request_name ?? '-',
            ],
            'company' => [
                'id' => $fixed_asset->company_id ?? '-',
                'company_code' => $fixed_asset->company_code ?? '-',
                'company_name' => $fixed_asset->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $fixed_asset->business_unit_id ?? '-',
                'business_unit_code' => $fixed_asset->business_unit_code ?? '-',
                'business_unit_name' => $fixed_asset->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $fixed_asset->department_id ?? '-',
                'department_code' => $fixed_asset->department_code ?? '-',
                'department_name' => $fixed_asset->department_name ?? '-',
            ],
            'unit' => [
                'id' => $fixed_asset->unit_id ?? '-',
                'unit_code' => $fixed_asset->unit_code ?? '-',
                'unit_name' => $fixed_asset->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $fixed_asset->subunit_id ?? '-',
                'subunit_code' => $fixed_asset->sub_unit_code ?? '-',
                'subunit_name' => $fixed_asset->sub_unit_name ?? '-',
            ],
            'charged_department' => [
                'id' => $fixed_asset->department_id ?? '-',
                'department_code' => $fixed_asset->department_code ?? '-',
                'department_name' => $fixed_asset->department_name ?? '-',
            ],
            'location' => [
                'id' => $fixed_asset->location_id ?? '-',
                'location_code' => $fixed_asset->location_code ?? '-',
                'location_name' => $fixed_asset->location_name ?? '-',
            ],
            /*            'account_title' => [
                            'id' => $fixed_asset->accountTitle->initialCredit->id ?? '-',
                            'account_title_code' => $fixed_asset->accountTitle->initialCredit->credit_code ?? '-',
                            'account_title_name' => $fixed_asset->accountTitle->initialCredit->credit_name ?? '-',
                        ],*/
            'initial_debit' => [
                'id' => $fixed_asset->initial_debit_id ?? '-',
                'account_title_code' => $fixed_asset->initial_debit_account_title_code ?? '-',
                'account_title_name' => $fixed_asset->initial_debit_account_title_name ?? '-',
                'depreciation_debit' => $fixed_asset->depreciation_debits ?? [],
            ],
            'initial_credit' => [
                'id' => $fixed_asset->initial_credit_id ?? '-',
                'account_title_code' => $fixed_asset->initial_credit_account_title_code ?? '-',
                'account_title_name' => $fixed_asset->initial_credit_account_title_name ?? '-',
            ],
            'depreciation_debit' => [
                'id' => $fixed_asset->depreciation_debit_id ?? '-',
                'account_title_code' => $fixed_asset->depreciation_debit_account_title_code ?? '-',
                'account_title_name' => $fixed_asset->depreciation_debit_account_title_name ?? '-',
            ],
            'depreciation_credit' => [
                'id' => $fixed_asset->depreciation_credit_id ?? '-',
                'account_title_code' => $fixed_asset->depreciation_credit_account_title_code ?? '-',
                'account_title_name' => $fixed_asset->depreciation_credit_account_title_name ?? '-',
            ],
            'created_at' => $fixed_asset->created_at,
        ];
    }


//    public function faIndex($ymir, $addCost, $movement = null, $subUnit = null, $smallTools = null): JsonResponse
//    {
//
//        $fixed_assets = FixedAsset::select([
//            'id', 'vladimir_tag_number', 'tag_number', 'tag_number_old', 'asset_description', 'receipt', 'acquisition_cost',
//            'quantity', 'accountability', 'accountable', 'asset_specification',
//            'from_request', 'is_released', 'formula_id', 'requester_id', 'uom_id',
//            'warehouse_number_id', 'capex_id', 'sub_capex_id', 'type_of_request_id', 'supplier_id',
//            'department_id', 'major_category_id', 'minor_category_id', 'asset_status_id',
//            'cycle_count_status_id', 'depreciation_status_id', 'movement_status_id',
//            'location_id', 'account_id', 'company_id', 'business_unit_id', 'unit_id', 'subunit_id', 'created_at'
//        ])->with([
//            'formula',
//            'additionalCost',
//            'requestor',
//            'warehouseNumber:id,warehouse_number',
//            'capex',
//            'subCapex',
//            'typeOfRequest:id,type_of_request_name',
//            'supplier:id,supplier_code,supplier_name',
//            'department.division:id,division_name',
//            'majorCategory:id,major_category_name',
//            'minorCategory:id,minor_category_name',
//            'assetStatus:id,asset_status_name',
//            'cycleCountStatus:id,cycle_count_status_name',
//            'depreciationStatus:id,depreciation_status_name',
//            'movementStatus:id,movement_status_name',
//            'company:id,company_name,company_code',
//            'businessUnit:id,business_unit_name,business_unit_code',
//            'department:id,department_name,department_code',
//            'unit:id,unit_name,unit_code',
//            'subunit:id,sub_unit_name,sub_unit_code',
//            'location:id,location_name,location_code',
//            'assetSmallTools',
//        ])->when($movement !== null, function ($query) use ($subUnit) {
//            if ($subUnit == null) {
//                $subUnit = auth('sanctum')->user()->subunit_id;
//            }
//            $query->where('subunit_id', $subUnit)
//                ->whereHas('assetStatus', function ($query) {
//                    $query->where('asset_status_name', 'Good');
//                })->Where(function ($query) {
//                    $query->where(function ($query) {
//                        $query->where(function ($query) {
//                            $query->WhereDoesntHave('transfer', function ($query) {
//                                $query->where('received_at', null);
//                            });
////                                    ->orW00 hereHas('transfer');
//                        })->where(function ($query) {
//                            $query->WhereDoesntHave('pullout', function ($query) {
//                                $query->where('evaluation', null);
//                            });
////                                    ->orWhereHas('pullout');
//                        });
//                    });
//                })
//                ->where(function ($query) {
//                    $query->where('from_request', 0)
//                        ->orWhere(function ($query) {
//                            $query->where('from_request', 1)
//                                ->where('is_released', 1);
//                        });
//                });
//
//            /*            ->when($movement !== null, function ($query) {
//$query->where('department_id', auth()->user()->department_id)
//->where(function ($query) {
//    $query->where('from_request', '!=', 1)
//        ->orWhere(function ($query) {
//            $query->where('from_request', 1)
//                ->where('is_released', 1)
//                ->where(function ($query) {
//                    $query->whereHas('transfer', function ($query) {
//                        $query->where('received_at', '!=', null);
//                    })->orWhereHas('pullout', function ($query) {
//                        $query->where('received_at', '!=', null);
//                    });
//                });
//        });
//});*/
//        })->when($ymir == true, function ($query) {
////                    ->where('from_request', 1)
//            $query->whereNotNull('depreciation_method')
//                ->where('is_released', 1);
//        })->when($addCost, function ($query) {
//            $query->whereNotNull('depreciation_method')
//                ->where(function ($query) {
//                    $query->where('from_request', 0)
//                        ->orWhere(function ($query) {
//                            $query->where('from_request', 1)
//                                ->where('is_released', 1);
//                        });
//                });
//        })->when($smallTools == true, function ($query) use ($subUnit) {
//            if ($subUnit == null) {
//                $subUnit = auth('sanctum')->user()->subunit_id;
//            }
//            //TODO: FOR MONITORING PA RIN ANG QUERY
//            $query->whereNotNull('depreciation_method') //, '!='
//            ->where('subunit_id', $subUnit)
//                ->whereHas('assetSmallTools')
//                ->whereHas('typeOfRequest', function ($query) {
//                    $query->whereIn('type_of_request_name', ['Small Tools', 'Small Tool']);
//                });
//        })
//            /*            ->where(function ($query) {
//                            $query->where('from_request', '!=', 1)
//                                ->orWhere(function ($query) {
//                                    $query->where('from_request', 1)
//                                        ->where('is_released', 1);
//                                });
//                        })*/
//            ->get();
//
//        /*        $fixed_assets = $fixed_assets->map(function ($fixedAsset) {
//                    $fixedAsset->transfer = $fixedAsset->isStillInTransferApproval() ? 1 : 0;
//                    return $fixedAsset;
//                });*/
//
//        return response()->json([
//            'message' => 'Fixed Assets retrieved successfully.',
//            'data' => $this->transformIndex($fixed_assets, $ymir)
//        ], 200);
//    }

    public function faIndex($ymir, $addCost, $movement = null, $subUnit = null, $smallTools = null)
    {
        $fixed_assets = DB::table('fixed_assets')
            ->select([
                'fixed_assets.id', 'fixed_assets.vladimir_tag_number', 'fixed_assets.tag_number', 'fixed_assets.tag_number_old',
                'fixed_assets.asset_description', 'fixed_assets.receipt', 'fixed_assets.acquisition_cost',
                'fixed_assets.quantity', 'fixed_assets.accountability', 'fixed_assets.accountable', 'fixed_assets.asset_specification',
                'fixed_assets.from_request', 'fixed_assets.is_released', 'fixed_assets.formula_id', 'fixed_assets.requester_id', 'fixed_assets.uom_id',
                'fixed_assets.warehouse_number_id', 'fixed_assets.capex_id', 'fixed_assets.sub_capex_id', 'fixed_assets.type_of_request_id', 'fixed_assets.supplier_id',
                'fixed_assets.department_id', 'fixed_assets.major_category_id', 'fixed_assets.minor_category_id', 'fixed_assets.asset_status_id',
                'fixed_assets.cycle_count_status_id', 'fixed_assets.depreciation_status_id', 'fixed_assets.movement_status_id',
                'fixed_assets.location_id', 'fixed_assets.account_id', 'fixed_assets.company_id', 'fixed_assets.business_unit_id',
                'fixed_assets.unit_id', 'fixed_assets.subunit_id', 'fixed_assets.created_at', 'fixed_assets.is_printable',
                // Formula data
                'formulas.acquisition_date',
                'formulas.depreciable_basis',

                // UOM data
                'unit_of_measures.uom_code',
                'unit_of_measures.uom_name',
                'unit_of_measures.sync_id as uom_sync_id',
                'unit_of_measures.id as uom_id',

                // Supplier data
                'suppliers.supplier_code',
                'suppliers.supplier_name',
                'suppliers.sync_id as supplier_sync_id',
                'suppliers.id as supplier_id',

                // User data
                'users.username as requestor_username',
                'users.firstname as requestor_firstname',
                'users.lastname as requestor_lastname',
                'users.employee_id as requestor_employee_id',
                'users.id as requestor_id',

                // Warehouse data
                'warehouse_numbers.warehouse_number as warehouse_number',
                'warehouse_numbers.id as warehouse_number_id',
                'warehouses.warehouse_name as warehouse_name',
                'warehouses.id as warehouse_id',

                //Capex
                'capexes.id as capex_id',
                'capexes.capex as capex_number',
                'capexes.project_name as capex_project_name',

                //SubCapex
                'sub_capexes.id as sub_capex_id',
                'sub_capexes.sub_capex as sub_capex_number',
                'sub_capexes.sub_project as sub_capex_project_name',

                // Type of Request data
                'type_of_requests.type_of_request_name',
                'type_of_requests.id as type_of_request_id',

                // Company data
                'companies.id as company_id',
                'companies.sync_id as company_sync_id',
                'companies.company_code as company_code',
                'companies.company_name as company_name',

                // Business Unit data
                'business_units.id as business_unit_id',
                'business_units.sync_id as business_unit_sync_id',
                'business_units.business_unit_code as business_unit_code',
                'business_units.business_unit_name as business_unit_name',

                // Department data
                'departments.id as department_id',
                'departments.sync_id as department_sync_id',
                'departments.department_code as department_code',
                'departments.department_name as department_name',

                // Unit data
                'units.id as unit_id',
                'units.sync_id as unit_sync_id',
                'units.unit_code as unit_code',
                'units.unit_name as unit_name',


                // Subunit data
                'sub_units.id as subunit_id',
                'sub_units.sync_id as subunit_sync_id',
                'sub_units.sub_unit_code as sub_unit_code',
                'sub_units.sub_unit_name as sub_unit_name',

                // Location data
                'locations.id as location_id',
                'locations.sync_id as location_sync_id',
                'locations.location_code as location_code',
                'locations.location_name as location_name',

                // Account data - you'll need to join these tables properly
                //INITIAL DEBIT
                'initial_debit.id as initial_debit_id',
                'initial_debit.sync_id as initial_debit_sync_id',
                'initial_debit.account_title_code as initial_debit_account_title_code',
                'initial_debit.account_title_name as initial_debit_account_title_name',

                //INITIAL CREDIT
                'initial_credit.id as initial_credit_id',
                'initial_credit.sync_id as initial_credit_sync_id',
                'initial_credit.credit_code as initial_credit_account_title_code',
                'initial_credit.credit_name as initial_credit_account_title_name',

                //DEPRECIATION DEBIT
                'depreciation_debit.id as depreciation_debit_id',
                'depreciation_debit.sync_id as depreciation_debit_sync_id',
                'depreciation_debit.account_title_code as depreciation_debit_account_title_code',
                'depreciation_debit.account_title_name as depreciation_debit_account_title_name',


                //DEPRECIATION CREDIT
                'depreciation_credit.id as depreciation_credit_id',
                'depreciation_credit.sync_id as depreciation_credit_sync_id',
                'depreciation_credit.credit_code as depreciation_credit_account_title_code',
                'depreciation_credit.credit_name as depreciation_credit_account_title_name',

                //DEPRECIATION HISTORY
//                'latest_depreciation.remaining_book_value',
                DB::raw('MAX(latest_depreciation.remaining_book_value) as remaining_book_value'),
                // Calculate additional costs data
                DB::raw('(SELECT COUNT(*) FROM additional_costs WHERE additional_costs.fixed_asset_id = fixed_assets.id) as additional_cost_count'),
                DB::raw('COALESCE((SELECT SUM(acquisition_cost) FROM additional_costs WHERE additional_costs.fixed_asset_id = fixed_assets.id), 0) as total_additional_cost')
            ])
            ->leftJoin('formulas', 'fixed_assets.formula_id', '=', 'formulas.id')
            ->leftJoin('additional_costs', 'fixed_assets.id', '=', 'additional_costs.fixed_asset_id')
            ->leftJoin('asset_small_tools', 'fixed_assets.id', '=', 'asset_small_tools.fixed_asset_id')
            ->leftJoin('warehouse_numbers', 'fixed_assets.warehouse_number_id', '=', 'warehouse_numbers.id')
            ->leftJoin('users', 'fixed_assets.requester_id', '=', 'users.id')
            ->leftJoin('unit_of_measures', 'fixed_assets.uom_id', '=', 'unit_of_measures.id')
            ->leftJoin('warehouses', 'fixed_assets.warehouse_number_id', '=', 'warehouses.id')
            ->leftJoin('capexes', 'fixed_assets.capex_id', '=', 'capexes.id')
            ->leftJoin('sub_capexes', 'fixed_assets.sub_capex_id', '=', 'sub_capexes.id')
            ->leftJoin('type_of_requests', 'fixed_assets.type_of_request_id', '=', 'type_of_requests.id')
            ->leftJoin('suppliers', 'fixed_assets.supplier_id', '=', 'suppliers.id')
            ->leftJoin('departments', 'fixed_assets.department_id', '=', 'departments.id')
            ->leftJoin('major_categories', 'fixed_assets.major_category_id', '=', 'major_categories.id')
            ->leftJoin('minor_categories', 'fixed_assets.minor_category_id', '=', 'minor_categories.id')
            ->leftJoin('asset_statuses', 'fixed_assets.asset_status_id', '=', 'asset_statuses.id')
            ->leftJoin('cycle_count_statuses', 'fixed_assets.cycle_count_status_id', '=', 'cycle_count_statuses.id')
            ->leftJoin('depreciation_statuses', 'fixed_assets.depreciation_status_id', '=', 'depreciation_statuses.id')
            ->leftJoin('movement_statuses', 'fixed_assets.movement_status_id', '=', 'movement_statuses.id')
            ->leftJoin('locations', 'fixed_assets.location_id', '=', 'locations.id')
            ->leftJoin('accounting_entries', 'fixed_assets.account_id', '=', 'accounting_entries.id')
            ->leftJoin('account_titles as initial_debit', 'accounting_entries.initial_debit', '=', 'initial_debit.id')
            ->leftJoin('credits as initial_credit', 'accounting_entries.initial_credit', '=', 'initial_credit.id')
            ->leftJoin('account_titles as depreciation_debit', 'accounting_entries.depreciation_debit', '=', 'depreciation_debit.id')
            ->leftJoin('credits as depreciation_credit', 'accounting_entries.depreciation_credit', '=', 'depreciation_credit.id')
            ->leftJoin('initial_debit_depreciation_debit', 'initial_debit.id', '=', 'initial_debit_depreciation_debit.initial_debit_id')
            ->leftJoin('account_titles as initial_debit_depreciation', 'initial_debit_depreciation_debit.depreciation_debit_id', '=', 'initial_debit_depreciation.id')
            ->leftJoin('companies', 'fixed_assets.company_id', '=', 'companies.id')
            ->leftJoin('business_units', 'fixed_assets.business_unit_id', '=', 'business_units.id')
            ->leftJoin('units', 'fixed_assets.unit_id', '=', 'units.id')
            ->leftJoin('sub_units', 'fixed_assets.subunit_id', '=', 'sub_units.id')
            ->leftJoin(DB::raw('(
    SELECT dh.fixed_asset_id, dh.remaining_book_value
    FROM depreciation_histories dh
    INNER JOIN (
        SELECT fixed_asset_id, MAX(created_at) as latest_date
        FROM depreciation_histories
        GROUP BY fixed_asset_id
    ) latest ON dh.fixed_asset_id = latest.fixed_asset_id AND dh.created_at = latest.latest_date
) as latest_depreciation'), 'fixed_assets.id', '=', 'latest_depreciation.fixed_asset_id')
            /*->with([
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
            'assetSmallTools',
        ])*/
            /*->with([
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
            'assetSmallTools',
        ])*/
            ->when($movement !== null, function ($query) use ($subUnit) {
                if ($subUnit == null) {
                    $subUnit = auth('sanctum')->user()->subunit_id;
                }

                $query->where('fixed_assets.subunit_id', $subUnit)
                    ->where('asset_statuses.asset_status_name', 'Good')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('transfers')
                            ->whereColumn('transfers.fixed_asset_id', 'fixed_assets.id')
                            ->where('transfers.deleted_at', null)
                            ->whereNull('transfers.received_at');
                    })
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('pullouts')
                            ->whereColumn('pullouts.fixed_asset_id', 'fixed_assets.id')
                            ->where('pullouts.deleted_at', null)
                            ->whereNull('pullouts.evaluation');
                    })
                    ->where(function ($query) {
                        $query->where('fixed_assets.from_request', 0)
                            ->orWhere(function ($query) {
                                $query->where('fixed_assets.from_request', 1)
                                    ->where('fixed_assets.is_released', 1);
//                                    ->where('fixed_assets.depreciation_method', '!=', null);
                            });
                    });

                /*            ->when($movement !== null, function ($query) {
    $query->where('department_id', auth()->user()->department_id)
    ->where(function ($query) {
        $query->where('from_request', '!=', 1)
            ->orWhere(function ($query) {
                $query->where('from_request', 1)
                    ->where('is_released', 1)
                    ->where(function ($query) {
                        $query->whereHas('transfer', function ($query) {
                            $query->where('received_at', '!=', null);
                        })->orWhereHas('pullout', function ($query) {
                            $query->where('received_at', '!=', null);
                        });
                    });
            });
    });*/
            })->when($ymir == true, function ($query) {
//                    ->where('from_request', 1)
                $query->whereNotNull('fixed_assets.depreciation_method')
                    ->where('fixed_assets.is_released', 1);
            })->when($addCost, function ($query) use ($subUnit) {
                $query->whereNotNull('fixed_assets.depreciation_method')
                    ->where(function ($query) {
                        $query->where('fixed_assets.from_request', 0)
                            ->orWhere(function ($query) {
                                $query->where('fixed_assets.from_request', 1)
                                    ->where('fixed_assets.is_released', 1);
                            });
                    })->when($subUnit, function ($query) use ($subUnit) {
                        $query->where('fixed_assets.subunit_id', $subUnit);
                    });
            })
            /*->when($subUnit, function ($query) use ($subUnit) {
                if ($subUnit == null) {
                    $subUnit = auth('sanctum')->user()->subunit_id;
                }
                $query->where('fixed_assets.subunit_id', $subUnit);
            })*/
            ->when($smallTools == true, function ($query) use ($subUnit) {
                if ($subUnit == null) {
                    $subUnit = auth('sanctum')->user()->subunit_id;
                }
                $query->whereNotNull('fixed_assets.depreciation_method')
                    ->where('asset_statuses.asset_status_name', 'Good')
                    ->where('fixed_assets.subunit_id', $subUnit)
                    ->whereIn('type_of_requests.type_of_request_name', ['Small Tools', 'Small Tool']);
            })
            /*            ->where(function ($query) {
                            $query->where('from_request', '!=', 1)
                                ->orWhere(function ($query) {
                                    $query->where('from_request', 1)
                                        ->where('is_released', 1);
                                });
                        })*/
            ->groupBy('fixed_assets.id', 'latest_depreciation.remaining_book_value')
            ->get();

        /*        $fixed_assets = $fixed_assets->map(function ($fixedAsset) {
                    $fixedAsset->transfer = $fixedAsset->isStillInTransferApproval() ? 1 : 0;
                    return $fixedAsset;
                });*/

        $fixed_assets = $this->attachSmallToolsData($fixed_assets);
        $fixed_assets = $this->attachDepreciationDebitData($fixed_assets); // Add this line

        return response()->json([
            'message' => 'Fixed Assets retrieved successfully.',
            'data' => $this->transformIndex($fixed_assets, $ymir)
        ], 200);
    }

    private function attachSmallToolsData($fixedAssets)
    {
        // Get all fixed asset IDs
        $assetIds = $fixedAssets->pluck('id')->toArray();

        // Fetch small tools for all assets in a single query
        $smallTools = DB::table('asset_small_tools')
            ->whereIn('fixed_asset_id', $assetIds)
            ->where('status_description', 'Good')
            ->select([
                'id', 'fixed_asset_id', 'description', 'specification',
                'pr_number', 'po_number', 'rr_number', 'acquisition_cost',
                'quantity', 'is_active as status', 'status_description'
            ])
            ->get()
            ->groupBy('fixed_asset_id');

        // Attach small tools to each fixed asset
        return $fixedAssets->map(function ($asset) use ($smallTools) {
            $asset->small_tools = isset($smallTools[$asset->id])
                ? $smallTools[$asset->id]->map(function ($item) {
                    return [
                        'id' => $item->id ?? '-',
                        'description' => $item->description ?? '-',
                        'specification' => $item->specification ?? '-',
                        'pr_number' => $item->pr_number ?? '-',
                        'po_number' => $item->po_number ?? '-',
                        'rr_number' => $item->rr_number ?? '-',
                        'acquisition_cost' => $item->acquisition_cost ?? '-',
                        'quantity' => $item->quantity ?? '-',
                        'status' => $item->status ?? '-',
                        'status_description' => $item->status_description,
                    ];
                })->toArray()
                : [];

            return $asset;
        });
    }

    private function attachDepreciationDebitData($fixedAssets)
    {
        // Get all initial_debit IDs
        $initialDebitIds = $fixedAssets->pluck('initial_debit_id')
            ->filter()
            ->unique()
            ->toArray();

        if (empty($initialDebitIds)) {
            return $fixedAssets;
        }

        // Fetch all depreciation debits for these initial_debits in a single query
        $depreciationDebits = DB::table('initial_debit_depreciation_debit')
            ->whereIn('initial_debit_id', $initialDebitIds)
            ->join('account_titles', 'initial_debit_depreciation_debit.depreciation_debit_id', '=', 'account_titles.id')
            ->select([
                'account_titles.id',
                'account_titles.sync_id',
                'account_titles.account_title_code',
                'account_titles.account_title_name',
                'account_titles.is_active',
                'account_titles.created_at',
                'account_titles.updated_at',
                'initial_debit_depreciation_debit.initial_debit_id',
                'initial_debit_depreciation_debit.depreciation_debit_id',
                'initial_debit_depreciation_debit.created_at as pivot_created_at',
                'initial_debit_depreciation_debit.updated_at as pivot_updated_at'
            ])
            ->get()
            ->groupBy('initial_debit_id');

        // Attach depreciation debits to each fixed asset's initial_debit
        return $fixedAssets->map(function ($asset) use ($depreciationDebits) {
            if (isset($asset->initial_debit_id) && isset($depreciationDebits[$asset->initial_debit_id])) {
                $asset->depreciation_debits = $depreciationDebits[$asset->initial_debit_id]->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'sync_id' => $item->sync_id,
                        'account_title_code' => $item->account_title_code,
                        'account_title_name' => $item->account_title_name,
                        'is_active' => (bool)$item->is_active,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                        'pivot' => [
                            'initial_debit_id' => $item->initial_debit_id,
                            'depreciation_debit_id' => $item->depreciation_debit_id,
                            'created_at' => $item->pivot_created_at,
                            'updated_at' => $item->pivot_updated_at
                        ]
                    ];
                })->toArray();
            } else {
                $asset->depreciation_debits = [];
            }
            return $asset;
        });
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
            'is_printable',
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
            DB::raw("NULL as is_printable"),
            'additional_costs.add_cost_sequence',
        ];
    }
}
