<?php

namespace App\Traits;

use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait AssetReleaseHandler
{
    public function paginateResults($items, $page = null, $perPage = 15, $options = []): LengthAwarePaginator
    {
        $page = $page ?: (LengthAwarePaginator::resolveCurrentPage() ?: 1);

        $items = $items instanceof Collection ? $items : Collection::make($items);

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

    public function searchFixedAsset($search, $page, $isReleased = 1, $per_page = null)
    {
        $results = $this->getSearchResults($isReleased);

        if (!empty($search)) {
            $results = $this->filterSearchResults($results, $search);
        }

        $results = $this->paginateResults($results, $page, $per_page);

        $results->setCollection($results->getCollection()->values());
        $results->getCollection()->transform(function ($item) {
            return $this->transformSearchFixedAsset($item);
        });

        return $results;
    }

    private function getSearchResults($isReleased = 1)
    {
        $fixedAssetFields = $this->getFixedAssetFields();
        $additionalCostFields = $this->getAdditionalCostFields();

        $firstQuery = FixedAsset::select($fixedAssetFields)
            ->where('from_request', 1)
            ->where('can_release', 1)
            ->where('is_released', $isReleased);

        $secondQuery = AdditionalCost::select($additionalCostFields)
            ->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            ->where('additional_costs.from_request', 1)
            ->where('additional_costs.can_release', 1)
            ->where('additional_costs.is_released', $isReleased);

        return $firstQuery->unionAll($secondQuery)->orderBy('vladimir_tag_number', 'asc')->get();
    }

    private function filterSearchResults($results, $search)
    {
        return $results->filter(function ($item) use ($search) {
            return $this->searchInMainAttributes($item, $search) || $this->searchInRelationAttributes($item, $search);
        });
    }

    private function getFixedAssetFields(): array
    {
        return [
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
            'can_release',
            'is_released',
            'asset_description',
            'type_of_request_id',
            'asset_specification',
            'accountability',
            'accountable',
            'received_by',
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
    }

    private function getAdditionalCostFields(): array
    {
        return [
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
            'additional_costs.can_release',
            'additional_costs.is_released',
            'additional_costs.asset_description',
            'additional_costs.type_of_request_id',
            'additional_costs.asset_specification',
            'additional_costs.accountability',
            'additional_costs.accountable',
            'additional_costs.received_by',
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
    }

    public function transformFixedAsset($fixed_asset): array
    {
        $fixed_assets_arr = [];
        foreach ($fixed_asset as $asset) {
            // Transform the current asset using the transformSingleFixedAsset method
            $fixed_assets_arr[] = $this->transformSingleFixedAsset($asset);
        }
        return $fixed_assets_arr;
    }

    public function transformSingleFixedAsset($fixed_asset): array
    {
            $signature = $fixed_asset->getMedia(Str::slug($fixed_asset->received_by) . '-signature')->first();
        return [
//            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'requestor' => [
                'id' => $fixed_asset->requestor->id ?? '-',
                'username' => $fixed_asset->requestor->username ?? '-',
                'firstname' => $fixed_asset->requestor->firstname ?? '-',
                'lastname' => $fixed_asset->requestor->lastname ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'po_number' => $fixed_asset->po_number ?? '-',
            'rr_number' => $fixed_asset->rr_number ?? '-',
            'is_released' => $fixed_asset->is_released ?? '-',
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
            'received_by' => $fixed_asset->received_by ?? '-',
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
            'signature' => $signature ? [
                'id' => $signature->id,
                'file_name' => $signature->file_name,
                'file_path' => $signature->getPath(),
                'file_url' => $signature->getUrl(),
                'collection_name' => $signature->collection_name,
                'viewing' => $this->convertImageToBase64($signature->getPath()),
            ] : null,
            'additional_cost' => isset($fixed_asset->additionalCost) ? $fixed_asset->additionalCost->map(function ($additional_cost) {
                return [
                    'id' => $additional_cost->id ?? '-',
                    'requestor' => [
                        'id' => $additional_cost->requestor->id ?? '-',
                        'username' => $additional_cost->requestor->username ?? '-',
                        'firstname' => $additional_cost->requestor->firstname ?? '-',
                        'lastname' => $additional_cost->requestor->lastname ?? '-',
                        'employee_id' => $additional_cost->requestor->employee_id ?? '-',
                    ],
                    'pr_number' => $additional_cost->pr_number ?? '-',
                    'po_number' => $additional_cost->po_number ?? '-',
                    'rr_number' => $additional_cost->rr_number ?? '-',
                    'is_released' => $additional_cost->is_released ?? '-',
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
                    'received_by' => $additional_cost->received_by ?? '-',
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
                'firstname' => $fixed_asset->requestor->firstname ?? '-',
                'lastname' => $fixed_asset->requestor->lastname ?? '-',
                'employee_id' => $fixed_asset->requestor->employee_id ?? '-',
            ],
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'po_number' => $fixed_asset->po_number ?? '-',
            'rr_number' => $fixed_asset->rr_number ?? '-',
            'is_released' => $fixed_asset->is_released ?? '-',
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
            'received_by' => $fixed_asset->received_by ?? '-',
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

    function searchInMainAttributes($item, $search): bool
    {
        $mainAttributes = [
            'vladimir_tag_number',
            'tag_number',
            'tag_number_old',
            'asset_description',
            'accountability',
            'accountable',
            'brand',
            'depreciation_method',
        ];

        // 'ready to print' specific case
        if (strtolower($search) == 'ready to tag' && $item->print_count < 1) {
            return true;
        }

        // 'printed' specific case
        if (strtolower($search) == 'tagged' && $item->print_count > 0) {
            return true;
        }

        foreach ($mainAttributes as $attribute) {
            if (stripos($item->$attribute, $search) !== false) {
                return true;
            }
        }
        return false;
    }

    function searchInRelationAttributes($item, $search): bool
    {
        $relationAttributes = [
            'subCapex' => ['sub_capex', 'sub_project'],
            'majorCategory' => ['major_category_name'],
            'minorCategory' => ['minor_category_name'],
            'department' => ['division', 'department_name'],
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
            if (!isset($item->$relation)) {
                continue;
            }

            foreach ($attributes as $attribute) {
                if (stripos($item->$relation->$attribute, $search) !== false) {
                    return true;
                }
            }
        }
        return false;
    }


    public function transformSingleAdditionalCost($additional_cost): array
    {
        $signature = $additional_cost->getMedia(Str::slug($additional_cost->received_by) . '-signature')->first();
        return [
            //            'total_adcost' => $this->calculationRepository->getTotalCost($additional_cost->fixedAsset->additionalCosts),
            'id' => $additional_cost->id,
            'add_cost_sequence' => $additional_cost->add_cost_sequence,
            'fixed_asset' => [
                'id' => $additional_cost->fixedAsset->id,
                'vladimir_tag_number' => $additional_cost->fixedAsset->vladimir_tag_number,
                'asset_description' => $additional_cost->fixedAsset->asset_description,
            ],
            'requestor_id' => [
                'id' => $additional_cost->requestor->id ?? '-',
                'username' => $additional_cost->requestor->username ?? '-',
                'firstname' => $additional_cost->requestor->firstname ?? '-',
                'lastname' => $additional_cost->requestor->lastname ?? '-',
                'employee_id' => $additional_cost->requestor->employee_id ?? '-',
            ],
            'pr_number' => $additional_cost->pr_number ?? '-',
            'po_number' => $additional_cost->po_number ?? '-',
            'rr_number' => $additional_cost->rr_number ?? '-',
            'is_released' => $additional_cost->is_released ?? '-',
            'warehouse_number' => [
                'id' => $additional_cost->warehouseNumber->id ?? '-',
                'warehouse_number' => $additional_cost->warehouseNumber->warehouse_number ?? '-',
            ],
            'from_request' => $additional_cost->from_request ?? '-',
            'can_release' => $additional_cost->can_release ?? '-',
            'capex' => [
                'id' => $additional_cost->fixedAsset->capex->id ?? '-',
                'capex' => $additional_cost->fixedAsset->capex->capex ?? '-',
                'project_name' => $additional_cost->fixedAsset->capex->project_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $additional_cost->fixedAsset->subCapex->id ?? '-',
                'sub_capex' => $additional_cost->fixedAsset->subCapex->sub_capex ?? '-',
                'sub_project' => $additional_cost->fixedAsset->subCapex->sub_project ?? '-',
            ],
            'vladimir_tag_number' => $additional_cost->fixedAsset->vladimir_tag_number,
            'tag_number' => $additional_cost->fixedAsset->tag_number,
            'tag_number_old' => $additional_cost->fixedAsset->tag_number_old,
            'asset_description' => $additional_cost->asset_description,
            'type_of_request' => [
                'id' => $additional_cost->typeOfRequest->id ?? '-',
                'type_of_request_name' => $additional_cost->typeOfRequest->type_of_request_name ?? '-',
            ],
            'asset_specification' => $additional_cost->asset_specification,
            'accountability' => $additional_cost->accountability,
            'accountable' => $additional_cost->accountable,
            'received_by' => $additional_cost->received_by ?? '-',
            'cellphone_number' => $additional_cost->cellphone_number,
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
            'est_useful_life' => $additional_cost->majorCategory->est_useful_life ?? '-',
            'voucher' => $additional_cost->voucher ?? '-',
            'voucher_date' => $additional_cost->voucher_date ?? '-',
            'receipt' => $additional_cost->receipt ?? '-',
            'quantity' => $additional_cost->quantity ?? '-',
            'depreciation_method' => $additional_cost->depreciation_method ?? '-',
            //                    'salvage_value' => $additional_cost->salvage_value,
            'acquisition_date' => $additional_cost->acquisition_date ?? '-',
            'acquisition_cost' => $additional_cost->acquisition_cost ?? 0,
            'scrap_value' => $additional_cost->formula->scrap_value ?? 0,
            'depreciable_basis' => $additional_cost->formula->depreciable_basis ?? 0,
            'accumulated_cost' => $additional_cost->formula->accumulated_cost ?? 0,
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
                'id' => $additional_cost->department->businessUnit->id ?? '-',
                'business_unit_code' => $additional_cost->department->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $additional_cost->department->businessUnit->business_unit_name ?? '-',
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
            'signature' => $signature ? [
                'id' => $signature->id,
                'file_name' => $signature->file_name,
                'file_path' => $signature->getPath(),
                'file_url' => $signature->getUrl(),
                'collection_name' => $signature->collection_name,
                'viewing' => $this->convertImageToBase64($signature->getPath()),
            ] : null,
            'main' => [
                'id' => $additional_cost->fixedAsset->id,
                'capex' => [
                    'id' => $additional_cost->fixedAsset->capex->id ?? '-',
                    'capex' => $additional_cost->fixedAsset->capex->capex ?? '-',
                    'project_name' => $additional_cost->fixedAsset->capex->project_name ?? '-',
                ],
                'sub_capex' => [
                    'id' => $additional_cost->fixedAsset->subCapex->id ?? '-',
                    'sub_capex' => $additional_cost->fixedAsset->subCapex->sub_capex ?? '-',
                    'sub_project' => $additional_cost->fixedAsset->subCapex->sub_project ?? '-',
                ],
                'vladimir_tag_number' => $additional_cost->fixedAsset->vladimir_tag_number,
                'tag_number' => $additional_cost->fixedAsset->tag_number,
                'tag_number_old' => $additional_cost->fixedAsset->tag_number_old,
                'asset_description' => $additional_cost->fixedAsset->asset_description,
                'type_of_request' => [
                    'id' => $additional_cost->fixedAsset->typeOfRequest->id ?? '-',
                    'type_of_request_name' => $additional_cost->fixedAsset->typeOfRequest->type_of_request_name ?? '-',
                ],
                'asset_specification' => $additional_cost->fixedAsset->asset_specification,
                'accountability' => $additional_cost->fixedAsset->accountability,
                'accountable' => $additional_cost->fixedAsset->accountable,
                'cellphone_number' => $additional_cost->fixedAsset->cellphone_number,
                'brand' => $additional_cost->fixedAsset->brand ?? '-',
                'division' => [
                    'id' => $additional_cost->fixedAsset->department->division->id ?? '-',
                    'division_name' => $additional_cost->fixedAsset->department->division->division_name ?? '-',
                ],
                'major_category' => [
                    'id' => $additional_cost->fixedAsset->majorCategory->id ?? '-',
                    'major_category_name' => $additional_cost->fixedAsset->majorCategory->major_category_name ?? '-',
                ],
                'minor_category' => [
                    'id' => $additional_cost->fixedAsset->minorCategory->id ?? '-',
                    'minor_category_name' => $additional_cost->fixedAsset->minorCategory->minor_category_name ?? '-',
                ],
                'est_useful_life' => $additional_cost->fixedAsset->majorCategory->est_useful_life ?? '-',
                'voucher' => $additional_cost->fixedAsset->voucher,
                'voucher_date' => $additional_cost->fixedAsset->voucher_date ?? '-',
                'receipt' => $additional_cost->fixedAsset->receipt,
                'quantity' => $additional_cost->fixedAsset->quantity,
                'depreciation_method' => $additional_cost->fixedAsset->depreciation_method,
                //                    'salvage_value' => $additional_cost->fixedAsset->salvage_value,
                'acquisition_date' => $additional_cost->fixedAsset->acquisition_date,
                'acquisition_cost' => $additional_cost->fixedAsset->acquisition_cost,
                'scrap_value' => $additional_cost->fixedAsset->formula->scrap_value,
                'depreciable_basis' => $additional_cost->fixedAsset->formula->depreciable_basis,
                'accumulated_cost' => $additional_cost->fixedAsset->formula->accumulated_cost,
                'asset_status' => [
                    'id' => $additional_cost->fixedAsset->assetStatus->id ?? '-',
                    'asset_status_name' => $additional_cost->fixedAsset->assetStatus->asset_status_name ?? '-',
                ],
                'cycle_count_status' => [
                    'id' => $additional_cost->fixedAsset->cycleCountStatus->id ?? '-',
                    'cycle_count_status_name' => $additional_cost->fixedAsset->cycleCountStatus->cycle_count_status_name ?? '-',
                ],
                'depreciation_status' => [
                    'id' => $additional_cost->fixedAsset->depreciationStatus->id ?? '-',
                    'depreciation_status_name' => $additional_cost->fixedAsset->depreciationStatus->depreciation_status_name ?? '-',
                ],
                'movement_status' => [
                    'id' => $additional_cost->fixedAsset->movementStatus->id ?? '-',
                    'movement_status_name' => $additional_cost->fixedAsset->movementStatus->movement_status_name ?? '-',
                ],
                'is_additional_cost' => $additional_cost->fixedAsset->is_additional_cost,
                'is_old_asset' => $additional_cost->fixedAsset->is_old_asset,
                'status' => $additional_cost->fixedAsset->is_active,
                'care_of' => $additional_cost->fixedAsset->care_of,
                'months_depreciated' => $additional_cost->fixedAsset->formula->months_depreciated,
                'end_depreciation' => $additional_cost->fixedAsset->formula->end_depreciation,
                'depreciation_per_year' => $additional_cost->fixedAsset->formula->depreciation_per_year,
                'depreciation_per_month' => $additional_cost->fixedAsset->formula->depreciation_per_month,
                'remaining_book_value' => $additional_cost->fixedAsset->formula->remaining_book_value,
                'release_date' => $additional_cost->fixedAsset->formula->release_date ?? '-',
                'start_depreciation' => $additional_cost->fixedAsset->formula->start_depreciation,
                'company' => [
                    'id' => $additional_cost->fixedAsset->department->company->id ?? '-',
                    'company_code' => $additional_cost->fixedAsset->department->company->company_code ?? '-',
                    'company_name' => $additional_cost->fixedAsset->department->company->company_name ?? '-',
                ],
                'department' => [
                    'id' => $additional_cost->fixedAsset->department->id ?? '-',
                    'department_code' => $additional_cost->fixedAsset->department->department_code ?? '-',
                    'department_name' => $additional_cost->fixedAsset->department->department_name ?? '-',
                ],
                'charged_department' => [
                    'id' => $additional_cost->department->id ?? '-',
                    'charged_department_code' => $additional_cost->department->department_code ?? '-',
                    'charged_department_name' => $additional_cost->department->department_name ?? '-',
                ],
                'location' => [
                    'id' => $additional_cost->fixedAsset->location->id ?? '-',
                    'location_code' => $additional_cost->fixedAsset->location->location_code ?? '-',
                    'location_name' => $additional_cost->fixedAsset->location->location_name ?? '-',
                ],
                'account_title' => [
                    'id' => $additional_cost->fixedAsset->accountTitle->id ?? '-',
                    'account_title_code' => $additional_cost->fixedAsset->accountTitle->account_title_code ?? '-',
                    'account_title_name' => $additional_cost->fixedAsset->accountTitle->account_title_name ?? '-',
                ],
                'remarks' => $additional_cost->fixedAsset->remarks,
                'print_count' => $additional_cost->fixedAsset->print_count,
                'last_printed' => $additional_cost->fixedAsset->last_printed,
            ],
        ];
    }

    private function convertImageToBase64($filePath): ?string
    {
        // Check if the file exists
        if (!file_exists($filePath)) {
            return null;
        }

        // Get the file type
        $type = pathinfo($filePath, PATHINFO_EXTENSION);

        // Get the file data
        $data = file_get_contents($filePath);

        // Convert the file data to base64
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

        return $base64;
    }
}
