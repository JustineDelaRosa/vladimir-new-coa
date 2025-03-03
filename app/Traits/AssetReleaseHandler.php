<?php

namespace App\Traits;

use App\Models\AdditionalCost;
use App\Models\AssetRequest;
use App\Models\AssetSmallTool;
use App\Models\FixedAsset;
use App\Models\Status\AssetStatus;
use App\Models\YmirPRTransaction;
use App\Repositories\CalculationRepository;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait AssetReleaseHandler
{
    use ApiResponse;

    protected CalculationRepository $calculationRepository;

    public function __construct(CalculationRepository $calculationRepository)
    {
        $this->calculationRepository = $calculationRepository;
    }

    public function assetReleaseActivityLog($AssetToRelease, $isClaimed = false)
    {
        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        activity()
            ->causedBy($user)
            ->performedOn($assetRequests)
            ->withProperties($this->composeLogPropertiesRelease($AssetToRelease))
            ->inLog($isClaimed ? 'Claimed' : "Asset Release $assetRequests->description")
            ->tap(function ($activity) use ($user, $AssetToRelease) {
                $firstAssetRequest = $AssetToRelease;
                if ($firstAssetRequest) {
                    $activity->subject_id = $AssetToRelease->transaction_number;
                }
            })
            ->log($isClaimed ? "Claimed by $assetRequests->received_by" : "Asset is Released by $user->employee_id - $user->firstname $user->lastname");
    }

    private function composeLogPropertiesRelease($assetToRelease): array
    {
        return [
            'transaction_number' => $assetToRelease->transaction_number,
            'description' => $assetToRelease->asset_description,
            'received_by' => $assetToRelease->received_by,
            'vladimir_tag' => $assetToRelease->vladimir_tag_number ?? $assetToRelease->fixedAsset->vladimir_tag_number ?? '-',
        ];
    }

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
        $userWarehouseId = auth('sanctum')->user()->warehouse_id;

        $firstQuery = FixedAsset::select($fixedAssetFields)
            ->where('from_request', 1)
            ->where('can_release', 1)
            ->where('is_released', $isReleased)
            ->where('warehouse_id', $userWarehouseId)
            ->where(function ($query) {
                $query->where('accountability', 'Common')
                    ->where('memo_series_id', null)
                    ->orWhere(function ($query) {
                        $query->where('accountability', 'Personal Issued');
//                            ->where('asset_condition', '!=', 'New');
                    })
                    ->orWhere(function ($query) {
                        $query->where('accountability', 'Personal Issued')
//                            ->where('asset_condition', 'New')
                            ->whereNotNull('memo_series_id');
                    });
            });

        $secondQuery = AdditionalCost::select($additionalCostFields)
            ->leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            ->where('additional_costs.from_request', 1)
            ->where('additional_costs.can_release', 1)
            ->where('additional_costs.is_released', $isReleased);
//            ->where('additional_costs.receiving_warehouse_id', $userWarehouseId);
        return $firstQuery->unionAll($secondQuery)->orderBy('created_at', 'desc')->get();
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
            'ymir_pr_number',
            'pr_number',
            'po_number',
            'rr_number',
            'warehouse_id',
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
            'unit_id',
            'subunit_id',
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

    private function getAdditionalCostFields(): array
    {
        return [
            'additional_costs.id',
            'additional_costs.requester_id',
            'additional_costs.ymir_pr_number',
            'additional_costs.pr_number',
            'additional_costs.po_number',
            'additional_costs.rr_number',
            'additional_costs.warehouse_id',
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
            'additional_costs.unit_id',
            'additional_costs.subunit_id',
            'fixed_assets.charged_department as charged_department',
            'additional_costs.location_id',
            'additional_costs.account_id',
            'additional_costs.remarks',
            'additional_costs.created_at',
            'fixed_assets.print_count',
            'fixed_assets.last_printed',
            DB::raw("0 as add_cost_sequence"),
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
        $receiverImg = $fixed_asset->getMedia('receiverImg')->first();
        $assignmentMemoImg = $fixed_asset->getMedia('assignmentMemoImg')->first();
        $authorizationMemoImg = $fixed_asset->getMedia('authorizationMemoImg')->first();
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
            'item_status' => $fixed_asset->asset_condition,
            'pr_number' => $fixed_asset->pr_number ?? '-',
            'po_number' => $fixed_asset->po_number ?? '-',
            'rr_number' => $fixed_asset->rr_number ?? '-',
            'is_released' => $fixed_asset->is_released ?? '-',
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
            /*            'small_tools_item' => $fixed_asset->assetSmallTools ?
                            $fixed_asset->assetSmallTools->map(function ($smallTool) {
                                return [
                                    'id' => $smallTool->id ?? '-',
                                    'sync_id' => $smallTool->item->sync_id ?? '-',
                                    'item_code' => $smallTool->item->item_code ?? '-',
                                    'item_name' => $smallTool->item->item_name ?? '-',
                                    'quantity' => $smallTool->quantity ?? '-',
                                    'status' => $smallTool->is_active ?? '-',
                                    'status_description' => $smallTool->to_release ? 'For Releasing' :
                                        $smallTool->status_description,
            //                        'items' => $smallTool->item ?? [],
                                ];
                            })
                            : [],*/
            'small_tools' => $fixed_asset->assetSmallTools ?
                $fixed_asset->assetSmallTools->map(function ($smallTool) {
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
                })
                : [],
            'item' => $fixed_asset->assetSmallTools->map(function ($item) {
                return [
                    'id' => $item->item->id ?? '-',
                    'item_code' => $item->item->item_code ?? '-',
                    'item_name' => $item->item->item_name ?? '-',
                    'quantity' => $item->quantity ?? '-',
                    'status_description' => $item->status_description ?? '-',
                    'status' => $item->is_active ?? '-',
                    'to_release' => $item->to_release ?? '-',
                ];
            }),
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
                'id' => $fixed_asset->company->id ?? '-',
                'company_code' => $fixed_asset->company->company_code ?? '-',
                'company_name' => $fixed_asset->company->company_name ?? '-',
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
                'id' => $fixed_asset->department->id ?? '-',
                'charged_department_code' => $fixed_asset->department->department_code ?? '-',
                'charged_department_name' => $fixed_asset->department->department_name ?? '-',
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
                'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->credit_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->credit_name ?? '-',
            ],
            'remarks' => $fixed_asset->remarks,
            'print_count' => $fixed_asset->print_count,
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $fixed_asset->last_printed,
            'is_printable' => $fixed_asset->is_printable,
            'tagging' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'receiverImg' => $receiverImg ? [
                'id' => $receiverImg->id,
                'file_name' => $receiverImg->file_name,
                'file_path' => $receiverImg->getPath(),
                'file_url' => $receiverImg->getUrl(),
                'collection_name' => $receiverImg->collection_name,
                'viewing' => $this->convertImageToBase64($receiverImg->getPath()),
            ] : null,
            'assignmentMemoImg' => $assignmentMemoImg ? [
                'id' => $assignmentMemoImg->id,
                'file_name' => $assignmentMemoImg->file_name,
                'file_path' => $assignmentMemoImg->getPath(),
                'file_url' => $assignmentMemoImg->getUrl(),
                'collection_name' => $assignmentMemoImg->collection_name,
                'viewing' => $this->convertImageToBase64($assignmentMemoImg->getPath()),
            ] : null,
            'authorizationMemoImg' => $authorizationMemoImg ? [
                'id' => $authorizationMemoImg->id,
                'file_name' => $authorizationMemoImg->file_name,
                'file_path' => $authorizationMemoImg->getPath(),
                'file_url' => $authorizationMemoImg->getUrl(),
                'collection_name' => $authorizationMemoImg->collection_name,
                'viewing' => $this->convertImageToBase64($authorizationMemoImg->getPath()),
            ] : null,
//            'signature' => $signature ? [
//                'id' => $signature->id,
//                'file_name' => $signature->file_name,
//                'file_path' => $signature->getPath(),
//                'file_url' => $signature->getUrl(),
//                'collection_name' => $signature->collection_name,
//                'viewing' => $this->convertImageToBase64($signature->getPath()),
//            ] : null,
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
                        'account_title_code' => $additional_cost->accountTitle->depreciationCredit->credit_code ?? '-',
                        'account_title_name' => $additional_cost->accountTitle->depreciationCredit->credit_name ?? '-',
                    ],
                    'remarks' => $additional_cost->remarks ?? '-',
                ];
            }) : [],
        ];
    }

    public function transformSearchFixedAsset($fixed_asset): array
    {
        $fixed_asset->additional_cost_count = $fixed_asset->additionalCost ? count($fixed_asset->additionalCost) : 0;
        try {
            $YmirPRNumber = YmirPRTransaction::where('pr_number', $fixed_asset->pr_number)->first()->pr_year_number_id ?? null;
        } catch (\Exception $e) {
            $YmirPRNumber = $fixed_asset->pr_number;
        }

        return [
            //'totalCost' => $this->calculationRepository->getTotalCost($fixed_asset->acquisition_cost, $fixed_asset->additionalCost),
            'additional_cost_count' => $fixed_asset->additional_cost_count,
            'id' => $fixed_asset->id,
            'ymir_pr_number' => $YmirPRNumber ?: '-',
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
            'warehouse' => [
                'id' => $fixed_asset->warehouse->id ?? '-',
                'warehouse_name' => $fixed_asset->warehouse->warehouse_name ?? '-',
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
                'id' => $fixed_asset->company->id ?? '-',
                'company_code' => $fixed_asset->company->company_code ?? '-',
                'company_name' => $fixed_asset->company->company_name ?? '-',
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
                'id' => $fixed_asset->department->id ?? '-',
                'charged_department_code' => $fixed_asset->department->department_code ?? '-',
                'charged_department_name' => $fixed_asset->department->department_name ?? '-',
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
                'account_title_code' => $fixed_asset->accountTitle->depreciationCredit->credit_code ?? '-',
                'account_title_name' => $fixed_asset->accountTitle->depreciationCredit->credit_name ?? '-',
            ],
            'remarks' => $fixed_asset->remarks ?? '-',
            'print_count' => $fixed_asset->print_count,
            'print' => $fixed_asset->print_count > 0 ? 'Tagged' : 'Ready to Tag',
            'last_printed' => $fixed_asset->last_printed ?? '-',
            'is_printable' => $fixed_asset->is_printable,
            'created_at' => $fixed_asset->created_at,
            'add_cost_sequence' => $fixed_asset->add_cost_sequence ?? null,
        ];
    }

    function searchInMainAttributes($item, $search): bool
    {
        $mainAttributes = [
            'vladimir_tag_number',
            'ymir_pr_number',
            'pr_number',
            'po_number',
            'rr_number',
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
//        $signature = $additional_cost->getMedia(Str::slug($additional_cost->received_by) . '-signature')->first();
        $receiverImg = $additional_cost->getMedia('receiverImg')->first();
        $assignmentMemoImg = $additional_cost->getMedia('assignmentMemoImg')->first();
        $authorizationMemoImg = $additional_cost->getMedia('authorizationMemoImg')->first();
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
            'warehouse' => [
                'id' => $additional_cost->warehouse->id ?? '-',
                'warehouse_name' => $additional_cost->warehouse->warehouse_name ?? '-',
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
            'is_printable' => 0,
            'company' => [
                'id' => $additional_cost->company->id ?? '-',
                'company_code' => $additional_cost->company->company_code ?? '-',
                'company_name' => $additional_cost->company->company_name ?? '-',
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
            'receiverImg' => $receiverImg ? [
                'id' => $receiverImg->id,
                'file_name' => $receiverImg->file_name,
                'file_path' => $receiverImg->getPath(),
                'file_url' => $receiverImg->getUrl(),
                'collection_name' => $receiverImg->collection_name,
                'viewing' => $this->convertImageToBase64($receiverImg->getPath()),
            ] : null,
            'assignmentMemoImg' => $assignmentMemoImg ? [
                'id' => $assignmentMemoImg->id,
                'file_name' => $assignmentMemoImg->file_name,
                'file_path' => $assignmentMemoImg->getPath(),
                'file_url' => $assignmentMemoImg->getUrl(),
                'collection_name' => $assignmentMemoImg->collection_name,
                'viewing' => $this->convertImageToBase64($assignmentMemoImg->getPath()),
            ] : null,
            'authorizationMemoImg' => $authorizationMemoImg ? [
                'id' => $authorizationMemoImg->id,
                'file_name' => $authorizationMemoImg->file_name,
                'file_path' => $authorizationMemoImg->getPath(),
                'file_url' => $authorizationMemoImg->getUrl(),
                'collection_name' => $authorizationMemoImg->collection_name,
                'viewing' => $this->convertImageToBase64($authorizationMemoImg->getPath()),
            ] : null,
//            'signature' => $signature ? [
//                'id' => $signature->id,
//                'file_name' => $signature->file_name,
//                'file_path' => $signature->getPath(),
//                'file_url' => $signature->getUrl(),
//                'collection_name' => $signature->collection_name,
//                'viewing' => $this->convertImageToBase64($signature->getPath()),
//            ] : null,
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
                'unit_of_measure' => [
                    'id' => $additional_cost->fixedAsset->uom->id ?? '-',
                    'uom_code' => $additional_cost->fixedAsset->uom->uom_code ?? '-',
                    'uom_name' => $additional_cost->fixedAsset->uom->uom_name ?? '-',
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
                'unit' => [
                    'id' => $assitional_cost->unit->id ?? '-',
                    'unit_code' => $assitional_cost->unit->unit_code ?? '-',
                    'unit_name' => $assitional_cost->unit->unit_name ?? '-',
                ],
                'subunit' => [
                    'id' => $assitional_cost->subunit->id ?? '-',
                    'subunit_code' => $assitional_cost->subunit->subunit_code ?? '-',
                    'subunit_name' => $assitional_cost->subunit->subunit_name ?? '-',
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

    public function convertImageToBase64($filePath): ?string
    {
        // Check if the file exists
        if (!file_exists($filePath)) {
            return null;
        }

        // Get the file type
        $type = pathinfo($filePath, PATHINFO_EXTENSION);

        // Get the file data
        $data = file_get_contents($filePath);

        // id $data is empty return null
//        if (empty($data)) {
//            return null;
//        }

        // Convert the file data to base64
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

        return $base64;
    }


    public function processAsset($assetQuery, $images, $receivedBy, $accountability, $accountable, $depreciation, $companyId, $businessUnitId, $departmentId, $unitId, $subunitId, $locationId)
    {
        $asset = (clone $assetQuery)->first();
        $asset->storeBase64Images($images);


        /*if ($asset->is_additional_cost) {
            $itemId = AssetRequest::where('reference_number', $asset->reference_number)->value('item_id');
            $assetSmallTools = AssetSmallTool::find($itemId);

            if ($assetSmallTools) {
                $newAssetSmallToolData = [
                    'quantity' => 1,
                    'is_active' => 1,
                    'status_description' => 'Good',
                    'pr_number' => $asset->ymir_pr_number,
                    'po_number' => $asset->po_number,
                    'rr_number' => $asset->rr_number,
                    'acquisition_cost' => $asset->acquisition_cost,
                ];

                if ($assetSmallTools->quantity == 1) {
                    $assetSmallTools->update(['is_active' => 0, 'status_description' => 'Replaced']);
                } else {
                    $assetSmallTools->decrement('quantity');
                }

                $assetSmallTools->refresh();
                $newAssetSmallTool = $assetSmallTools->replicate();
                $newAssetSmallTool->fill($newAssetSmallToolData);
                $newAssetSmallTool->save();
            }
        }*/

        if ($asset->is_additional_cost) {
            $this->assetSmallToolUpdate($asset);
        }

        $updateData = [
//            'accountability' => $accountability,
//            'accountable' => $accountable,
            'received_by' => $receivedBy,
            'is_released' => 1,
            'depreciation_status_id' => $depreciation,
        ];

        if ($companyId !== null) {
            $updateData['company_id'] = $companyId;
        }
        if ($businessUnitId !== null) {
            $updateData['business_unit_id'] = $businessUnitId;
        }
        if ($departmentId !== null) {
            $updateData['department_id'] = $departmentId;
        }
        if ($unitId !== null) {
            $updateData['unit_id'] = $unitId;
        }
        if ($subunitId !== null) {
            $updateData['subunit_id'] = $subunitId;
        }
        if ($locationId !== null) {
            $updateData['location_id'] = $locationId;
        }
//        if ($accountTitleId !== null) {
//            $updateData['account_id'] = $accountTitleId;
//        }

//        $acquisitionCost = $asset->acquisition_cost;
//        $depreciationMethod = $acquisitionCost < 10000 ? 'One Time' : 'STL';
//        $updateData['depreciation_method'] = $depreciationMethod;

        (clone $assetQuery)->update($updateData);
        $formula = $asset->formula;
        //check the acquisition cost if below 10,000 One Time else Straight Line

        $formula->update([
            'release_date' => now()->format('Y-m-d'),
//            'start_depreciation' => $this->calculationRepository->getStartDepreciation($depreciationMethod, now()->format('Y-m-d')),
//            'end_depreciation' => $this->calculationRepository->getEndDepreciation(now()->format('Y-m-d'), $asset->majorCategory->est_useful_life, $depreciationMethod), //TODO: temporary est useful life
//            'depreciation_method' => $depreciationMethod,
        ]);
        $asset->refresh();
        $this->assetReleaseActivityLog($asset);
        $this->assetReleaseActivityLog($asset, true);

        return $asset;
    }


    private function assetSmallToolUpdate($asset)
    {
        $itemId = AssetRequest::where('reference_number', $asset->reference_number)->value('item_id');

        if (!$itemId) {
            return $this->responseUnprocessable('Asset request not found for this reference number');
        }

        $assetSmallTools = AssetSmallTool::find($itemId);

        if (!$assetSmallTools) {
            return $this->responseUnprocessable('Asset Small Tool not found');
        }

        // Check if there's an existing replaced item with same specifications
        $existingReplaced = $this->findExistingReplacedItem($assetSmallTools);

        if ($assetSmallTools->quantity == 1) {
            // Handle single quantity item
            $this->handleSingleQuantityItem($assetSmallTools);
        } else {
            // Handle multiple quantity item
            $assetSmallTools->quantity -= 1;
            $assetSmallTools->save();

            if ($existingReplaced) {
                $this->updateExistingReplacedItem($existingReplaced, $assetSmallTools);
            } else {
                $this->createNewReplacedItem($assetSmallTools);
            }
        }

        // Handle the good condition item
        $existingGoodItem = $this->findExistingGoodItem($assetSmallTools);

        if ($existingGoodItem) {
            $this->updateExistingGoodItem($existingGoodItem);
        } else {
            $this->createNewGoodItem($assetSmallTools, $asset);
        }

        return true;
    }

    private function findExistingReplacedItem($assetSmallTools)
    {
        return AssetSmallTool::withTrashed()
            ->where('status_description', 'Replaced')
            ->where('reference_number', $assetSmallTools->reference_number)
            ->where('description', $assetSmallTools->description)
            ->where('specification', $assetSmallTools->specification)
            ->first();
    }

    private function handleSingleQuantityItem($assetSmallTools)
    {
        // If quantity is 1, update the current item to replaced
        $assetSmallTools->update([
            'is_active' => 0,
            'status_description' => 'Replaced'
        ]);
        $assetSmallTools->delete();

        $this->updateAdditionalCost($assetSmallTools->reference_number);
    }

    private function updateExistingReplacedItem($existingReplaced, $assetSmallTools)
    {
        // If replaced item exists, increment its quantity
        $existingReplaced->quantity += 1;
        $existingReplaced->save();

        $this->updateAdditionalCost($assetSmallTools->reference_number);
    }

    private function createNewReplacedItem($assetSmallTools)
    {
        // If no replaced item exists, create a new one
        $replacedItemData = $assetSmallTools->toArray();
        $replacedItemData['quantity'] = 1;
        $replacedItemData['is_active'] = 0;
        $replacedItemData['reference_number'] = $assetSmallTools->reference_number;
        $replacedItemData['status_description'] = 'Replaced';

        $replacedItem = new AssetSmallTool($replacedItemData);
        unset($replacedItem->id);
        $replacedItem->save();
        $replacedItem->delete();

        $this->deleteAdditionalCost($assetSmallTools->reference_number);
    }

    private function updateAdditionalCost($referenceNumber)
    {
        $additionalCost = AdditionalCost::where('reference_number', $referenceNumber)->first();
        if ($additionalCost) {
            $assetStatusId = AssetStatus::where('asset_status_name', 'Replaced')->value('id') ?? 1;
            $additionalCost->update(['asset_status_id' => $assetStatusId]);
        }
    }

    private function deleteAdditionalCost($referenceNumber)
    {
        $additionalCost = AdditionalCost::where('reference_number', $referenceNumber)->first();
        if (!$additionalCost) {
            return $this->responseUnprocessable('Additional Cost not found');
        }
        $additionalCost->delete();
    }

    private function findExistingGoodItem($assetSmallTools)
    {
        return AssetSmallTool::withTrashed()
            ->where('id', $assetSmallTools->id)
            ->where('reference_number', $assetSmallTools->reference_number)
            ->where('status_description', 'Good')
            ->where('description', $assetSmallTools->description)
            ->where('specification', $assetSmallTools->specification)
            ->first();
    }

    private function updateExistingGoodItem($existingGoodItem)
    {
        $existingGoodItem->quantity += 1;
        $existingGoodItem->save();
    }

    private function createNewGoodItem($assetSmallTools, $asset)
    {
        $newAssetSmallToolData = [
            'quantity' => 1,
            'is_active' => 1,
            'status_description' => 'Good',
            'pr_number' => $asset->ymir_pr_number,
            'po_number' => $asset->po_number,
            'rr_number' => $asset->rr_number,
            'acquisition_cost' => $asset->acquisition_cost,
            'reference_number' => $asset->reference_number,
            'deleted_at' => null,
        ];

        $assetSmallTools->refresh();
        $newAssetSmallTool = $assetSmallTools->replicate();
        $newAssetSmallTool->fill($newAssetSmallToolData);
        unset($newAssetSmallTool->id);
        $newAssetSmallTool->save();
    }

    /*    private function assetSmallToolUpdate($asset)
        {
            $itemId = AssetRequest::where('reference_number', $asset->reference_number)->value('item_id');
            $assetSmallTools = AssetSmallTool::find($itemId);

            if ($assetSmallTools) {
                // Check if there's an existing item with same specifications and "Good" status


                // Check if there's an existing replaced item with same specifications
                $existingReplaced = AssetSmallTool::withTrashed()
                    //                ->where('id', $assetSmallTools->id)
                    ->where('status_description', 'Replaced')
                    ->where('reference_number', $assetSmallTools->reference_number)
                    ////                ->where('acquisition_cost', $asset->acquisition_cost)
                    ->where('description', $assetSmallTools->description)
                    ->where('specification', $assetSmallTools->specification)
                    ->first();
                //            return $existingReplaced;

                if ($assetSmallTools->quantity == 1) {
                    // If quantity is 1, update the current item to replaced
                    $assetSmallTools->update(['is_active' => 0, 'status_description' => 'Replaced']);
                    $assetSmallTools->delete();
                    $additionalCost = AdditionalCost::where('reference_number', $assetSmallTools->reference_number)
                        ->where('from_request', 1)
                        ->first();
    //                dd($additionalCost);
                    if ($additionalCost) {
                        $assetStatusId = AssetStatus::where('asset_status_name', 'Replaced')->value('id') ?? 1;
                        $additionalCost->update(['asset_status_id' => $assetStatusId]);
                        $additionalCost->delete();
                    }

                } else {
                    // If quantity > 1, decrement and handle replaced item
                    $assetSmallTools->quantity -= 1;
                    //                $assetSmallTools->decrement('quantity');

                    if ($existingReplaced) {
                        // If replaced item exists, increment its quantity
                        $existingReplaced->quantity += 1;
                        $existingReplaced->save();

                        $additionalCost = AdditionalCost::where('reference_number', $assetSmallTools->reference_number)->first();
                        if ($additionalCost) {
                            $assetStatusId = AssetStatus::where('asset_status_name', 'Replaced')->value('id') ?? 1;
                            $additionalCost->update(['asset_status_id' => $assetStatusId]);
                        }

                        //                     $existingReplaced->increment('quantity');
                    } else {
                        // If no replaced item exists, create a new one
                        $replacedItemData = $assetSmallTools->toArray();
                        $replacedItemData['quantity'] = 1;
                        $replacedItemData['is_active'] = 0;
                        $replacedItemData['reference_number'] = $assetSmallTools->reference_number;
                        $replacedItemData['status_description'] = 'Replaced';

                        $replacedItem = new AssetSmallTool($replacedItemData);
                        unset($replacedItem->id);
                        $replacedItem->save();
                        $replacedItem->delete();

                        $additionalCost = AdditionalCost::where('reference_number', $assetSmallTools->reference_number)->first();
                        if (!$additionalCost) {
                            return $this->responseUnprocessable('Additional Cost not found');
                        }
                        $additionalCost->delete();
                    }
                }


                $existingGoodItem = AssetSmallTool::withTrashed()->where('id', $assetSmallTools->id)
                    ->where('reference_number', $assetSmallTools->reference_number)
                    ->where('status_description', 'Good')
                    //                ->where('acquisition_cost', $asset->acquisition_cost)
                    ->where('description', $assetSmallTools->description)
                    ->where('specification', $assetSmallTools->specification)
                    ->first();

                // Handle the good condition item
                if ($existingGoodItem) {
                    // If good item exists, increment its quantity

                    $existingGoodItem->quantity += 1;
                    $existingGoodItem->save();
                    //                $existingGoodItem->increment('quantity');
                } else {
                    // If no good item exists, create a new one
                    $newAssetSmallToolData = [
                        'quantity' => 1,
                        'is_active' => 1,
                        'status_description' => 'Good',
                        'pr_number' => $asset->ymir_pr_number,
                        'po_number' => $asset->po_number,
                        'rr_number' => $asset->rr_number,
                        'acquisition_cost' => $asset->acquisition_cost,
                        'reference_number' => $asset->reference_number,
                        'deleted_at' => null,
                    ];

                    $assetSmallTools->refresh();
                    $newAssetSmallTool = $assetSmallTools->replicate();
                    $newAssetSmallTool->fill($newAssetSmallToolData);
                    unset($newAssetSmallTool->id);
                    $newAssetSmallTool->save();
                }
            } else {
                return $this->responseUnprocessable('Asset Small Tool not found');
            }
        }*/

    public function updateRemoveMemoTag($fixedAsset, $additionalCost)
    {
        if ($fixedAsset !== null) {
            $fixedAsset->update(['memo_series_id' => null]);
        }
        if ($additionalCost !== null) {
            $additionalCost->update(['memo_series_id' => null]);
        }
    }

    public function setNewAccountability($fixedAsset, $additionalCost, $accountability, $accountable, $isCommon = false)
    {
        if ($fixedAsset !== null) {
            $updateData = [
                'accountability' => $accountability,
                'accountable' => $accountable,
            ];

            if (!$isCommon) {
                $updateData['can_release'] = 0;
            }

            $fixedAsset->update($updateData);
        }

        if ($additionalCost !== null) {
            $updateData = [
                'accountability' => $accountability,
                'accountable' => $accountable,
            ];

            if (!$isCommon) {
                $updateData['can_release'] = 0;
            }

            $additionalCost->update($updateData);
        }
    }
}
