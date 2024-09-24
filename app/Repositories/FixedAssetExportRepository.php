<?php

namespace App\Repositories;

use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Models\Formula;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FixedAssetExportRepository
{
    protected $calculationRepository;

    public function __construct()
    {
        $this->calculationRepository = new CalculationRepository();
    }

    public function export($search = null, $startDate = null, $endDate = null)
    {
        $fixedAsset = $this->prepareFixedAssetQuery($search, $startDate, $endDate);
        $additionalCost = $this->prepareAdditionalCostQuery($search, $startDate, $endDate);

        return $fixedAsset->unionAll($additionalCost)->orderBy('asset_description', 'ASC')->get();
    }

    private function prepareFixedAssetQuery($search, $startDate, $endDate)
    {
        $fixedAsset = FixedAsset::leftJoin('users', 'fixed_assets.requester_id', '=', 'users.id')
            ->leftJoin('type_of_requests', 'fixed_assets.type_of_request_id', '=', 'type_of_requests.id')
            ->leftJoin('suppliers', 'fixed_assets.supplier_id', '=', 'suppliers.id')
            ->leftJoin('companies', 'fixed_assets.company_id', '=', 'companies.id')
            ->leftJoin('business_units', 'fixed_assets.business_unit_id', '=', 'business_units.id')
            ->leftJoin('departments', 'fixed_assets.department_id', '=', 'departments.id')
            ->leftJoin('units', 'fixed_assets.unit_id', '=', 'units.id')
            ->leftJoin('sub_units', 'fixed_assets.subunit_id', '=', 'sub_units.id')
            ->leftJoin('locations', 'fixed_assets.location_id', '=', 'locations.id')
            ->leftJoin('account_titles', 'fixed_assets.account_id', '=', 'account_titles.id')
            ->leftJoin('formulas', 'fixed_assets.formula_id', '=', 'formulas.id')
            ->leftjoin('capexes', 'fixed_assets.capex_id', '=', 'capexes.id')
            ->leftjoin('sub_capexes', 'fixed_assets.sub_capex_id', '=', 'sub_capexes.id')
            ->leftJoin('major_categories', 'fixed_assets.major_category_id', '=', 'major_categories.id')
            ->leftJoin('minor_categories', 'fixed_assets.minor_category_id', '=', 'minor_categories.id')
            ->leftJoin('accounting_entries', 'fixed_assets.account_id', '=', 'accounting_entries.id')
            ->leftJoin('account_titles as initial_debit', 'accounting_entries.initial_debit', '=', 'initial_debit.id')
            ->leftJoin('account_titles as initial_credit', 'accounting_entries.initial_credit', '=', 'initial_credit.id')
            ->leftJoin('account_titles as depreciation_debit', 'accounting_entries.depreciation_debit', '=', 'depreciation_debit.id')
            ->leftJoin('account_titles as depreciation_credit', 'accounting_entries.depreciation_credit', '=', 'depreciation_credit.id')
            ->leftJoin('divisions', 'departments.division_id', '=', 'divisions.id')
            ->leftJoin('asset_statuses', 'fixed_assets.asset_status_id', '=', 'asset_statuses.id')
            ->leftJoin('cycle_count_statuses', 'fixed_assets.cycle_count_status_id', '=', 'cycle_count_statuses.id')
            ->leftJoin('depreciation_statuses', 'fixed_assets.depreciation_status_id', '=', 'depreciation_statuses.id')
            ->leftJoin('movement_statuses', 'fixed_assets.movement_status_id', '=', 'movement_statuses.id')
            ->whereNotNull('fixed_assets.major_category_id');

        if ($search) {
            $fixedAsset->where(function ($query) use ($search) {
                $query->where('users.username', 'LIKE', "%{$search}%")
                    ->orWhere('capexes.capex', 'LIKE', "%{$search}%")
                    ->orWhere('asset_description', 'LIKE', "%{$search}%")
                    ->orWhere('transaction_number', 'LIKE', "%{$search}%")
                    ->orWhere('reference_number', 'LIKE', "%{$search}%")
                    ->orWhere('pr_number', 'LIKE', "%{$search}%")
                    ->orWhere('po_number', 'LIKE', "%{$search}%")
                    ->orWhere('asset_specification', 'LIKE', "%{$search}%")
                    ->orWhere('vladimir_tag_number', 'LIKE', "%{$search}%")
                    ->orWhere('tag_number', 'LIKE', "%{$search}%")
                    ->orWhere('tag_number_old', 'LIKE', "%{$search}%")
                    ->orWhere('accountability', 'LIKE', "%{$search}%")
                    ->orWhere('accountable', 'LIKE', "%{$search}%")
                    ->orWhere('brand', 'LIKE', "%{$search}%")
                    ->orWhere('fixed_assets.depreciation_method', 'LIKE', "%{$search}%")
                    ->orWhere('type_of_requests.type_of_request_name', 'LIKE', "%{$search}%")
                    ->orWhere('suppliers.supplier_name', 'LIKE', "%{$search}%")
                    ->orWhere('major_categories.major_category_name', 'LIKE', "%{$search}%")
                    ->orWhere('minor_categories.minor_category_name', 'LIKE', "%{$search}%")
                    ->orWhere('divisions.division_name', 'LIKE', "%{$search}%")
                    ->orWhere('asset_statuses.asset_status_name', 'LIKE', "%{$search}%")
                    ->orWhere('cycle_count_statuses.cycle_count_status_name', 'LIKE', "%{$search}%")
                    ->orWhere('depreciation_statuses.depreciation_status_name', 'LIKE', "%{$search}%")
                    ->orWhere('movement_statuses.movement_status_name', 'LIKE', "%{$search}%")
                    ->orWhere('locations.location_name', 'LIKE', "%{$search}%")
                    ->orWhere('companies.company_name', 'LIKE', "%{$search}%")
                    ->orWhere('business_units.business_unit_name', 'LIKE', "%{$search}%")
                    ->orWhere('departments.department_name', 'LIKE', "%{$search}%")
                    ->orWhere('units.unit_name', 'LIKE', "%{$search}%")
                    ->orWhere('sub_units.sub_unit_name', 'LIKE', "%{$search}%")
                    ->orWhere('initial_debit.account_title_name', 'LIKE', "%{$search}%")
                    ->orWhere('initial_credit.account_title_name', 'LIKE', "%{$search}%")
                    ->orWhere('depreciation_debit.account_title_name', 'LIKE', "%{$search}%")
                    ->orWhere('depreciation_credit.account_title_name', 'LIKE', "%{$search}%");
//                    ->orWhere('account_titles.account_title_name', 'LIKE', "%{$search}%");
            });
        }

        if ($startDate && $endDate) {
            $fixedAsset->whereDate('fixed_assets.created_at', '>=', $startDate)
                ->whereDate('fixed_assets.created_at', '<=', $endDate);
        }

        $fixedAsset = $fixedAsset->select(
            'fixed_assets.id',
            'users.username as requester',
            'capexes.capex as capex',
            'capexes.project_name as project_name',
            'sub_capexes.sub_capex as sub_capex',
            'sub_capexes.sub_project as sub_project',
            'transaction_number',
            'reference_number',
            'pr_number',
            'po_number',
            'vladimir_tag_number',
            'tag_number',
            'tag_number_old',
            'asset_description',
            'asset_specification',
            'type_of_requests.type_of_request_name as type_of_request',
            'suppliers.supplier_name as supplier',
            'accountability',
            'accountable',
            'received_by',
            'capitalized',
            'cellphone_number',
            'brand',
            'major_categories.major_category_name as major_category',
            'minor_categories.minor_category_name as minor_category',
            'divisions.division_name as division',
            'voucher',
            'voucher_date',
            'receipt',
            'quantity',
            'fixed_assets.acquisition_date',
            'fixed_assets.acquisition_cost',
            'charged_department',
            'asset_statuses.asset_status_name as asset_status',
            'cycle_count_statuses.cycle_count_status_name as cycle_count_status',
            'depreciation_statuses.depreciation_status_name as depreciation_status',
            'movement_statuses.movement_status_name as movement_status',
            'care_of',
            'companies.company_code as company_code',
            'companies.company_name as company_name',
            'business_units.business_unit_code as business_unit_code',
            'business_units.business_unit_name as business_unit_name',
            'departments.department_code as department_code',
            'departments.department_name as department_name',
            'units.unit_code as unit_code',
            'units.unit_name as unit_name',
            'sub_units.sub_unit_code as sub_unit_code',
            'sub_units.sub_unit_name as sub_unit_name',
            'locations.location_code as location_code',
            'locations.location_name as location_name',
            'minor_categories.accounting_entries_id as accounting_entries_id',
//            'account_titles.account_title_code as account_title_code',
//            'account_titles.account_title_name as account_title_name',
            'initial_debit.account_title_name as initial_debit_name',
            'initial_debit.account_title_code as initial_debit_code',
            'initial_credit.account_title_name as initial_credit_name',
            'initial_credit.account_title_code as initial_credit_code',
            'depreciation_debit.account_title_name as depreciation_debit_name',
            'depreciation_debit.account_title_code as depreciation_debit_code',
            'depreciation_credit.account_title_name as depreciation_credit_name',
            'depreciation_credit.account_title_code as depreciation_credit_code',

            DB::raw('NULL as add_cost_sequence'),
            'formulas.depreciation_method',
            'formulas.scrap_value',
            'major_categories.est_useful_life', // todo: subject to monitor for possible bug
            'formulas.depreciable_basis',
            'formulas.months_depreciated',
            'formulas.end_depreciation',
            'formulas.depreciation_per_year',
            'formulas.depreciation_per_month',
            'formulas.accumulated_cost',
            'formulas.remaining_book_value',
            'formulas.release_date',
            'formulas.start_depreciation',
//                'fixed_assets.created_at'
        );
        return $fixedAsset;
    }

    private function prepareAdditionalCostQuery($search, $startDate, $endDate)
    {
        $additionalCost = AdditionalCost::leftJoin('fixed_assets', 'additional_costs.fixed_asset_id', '=', 'fixed_assets.id')
            ->leftJoin('users', 'additional_costs.requester_id', '=', 'users.id')
            ->leftJoin('type_of_requests', 'additional_costs.type_of_request_id', '=', 'type_of_requests.id')
            ->leftJoin('suppliers', 'additional_costs.supplier_id', '=', 'suppliers.id')
            ->leftJoin('companies', 'additional_costs.company_id', '=', 'companies.id')
            ->leftJoin('business_units', 'additional_costs.business_unit_id', '=', 'business_units.id')
            ->leftJoin('departments', 'additional_costs.department_id', '=', 'departments.id')
            ->leftJoin('units', 'additional_costs.unit_id', '=', 'units.id')
            ->leftJoin('sub_units', 'additional_costs.subunit_id', '=', 'sub_units.id')
            ->leftJoin('locations', 'additional_costs.location_id', '=', 'locations.id')
            ->leftJoin('account_titles', 'additional_costs.account_id', '=', 'account_titles.id')
            ->leftJoin('formulas', 'additional_costs.formula_id', '=', 'formulas.id')
            ->leftJoin('capexes', 'fixed_assets.capex_id', '=', 'capexes.id')
            ->leftJoin('sub_capexes', 'fixed_assets.sub_capex_id', '=', 'sub_capexes.id')
            ->leftJoin('major_categories', 'fixed_assets.major_category_id', '=', 'major_categories.id')
            ->leftJoin('minor_categories', 'fixed_assets.minor_category_id', '=', 'minor_categories.id')
            ->leftJoin('accounting_entries', 'fixed_assets.account_id', '=', 'accounting_entries.id')
            ->leftJoin('account_titles as initial_debit', 'accounting_entries.initial_debit', '=', 'initial_debit.id')
            ->leftJoin('account_titles as initial_credit', 'accounting_entries.initial_credit', '=', 'account_titles.id')
            ->leftJoin('account_titles as depreciation_debit', 'accounting_entries.depreciation_debit', '=', 'account_titles.id')
            ->leftJoin('account_titles as depreciation_credit', 'accounting_entries.depreciation_credit', '=', 'account_titles.id')
            ->leftJoin('divisions', 'departments.division_id', '=', 'divisions.id')
            ->leftJoin('asset_statuses', 'fixed_assets.asset_status_id', '=', 'asset_statuses.id')
            ->leftJoin('cycle_count_statuses', 'fixed_assets.cycle_count_status_id', '=', 'cycle_count_statuses.id')
            ->leftJoin('depreciation_statuses', 'fixed_assets.depreciation_status_id', '=', 'depreciation_statuses.id')
            ->leftJoin('movement_statuses', 'fixed_assets.movement_status_id', '=', 'movement_statuses.id')
            ->whereNotNull('additional_costs.major_category_id');

        if ($search) {
            $additionalCost->where(function ($query) use ($search) {
                $query->where('users.username', 'LIKE', "%{$search}%")
                    ->orWhere('capexes.capex', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.asset_description', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.asset_specification', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.transaction_number', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.reference_number', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.pr_number', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.po_number', 'LIKE', "%{$search}%")
                    ->orWhere('fixed_assets.vladimir_tag_number', 'LIKE', "%{$search}%")
                    ->orWhere('fixed_assets.tag_number', 'LIKE', "%{$search}%")
                    ->orWhere('fixed_assets.tag_number_old', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.accountability', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.accountable', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.brand', 'LIKE', "%{$search}%")
                    ->orWhere('additional_costs.depreciation_method', 'LIKE', "%{$search}%")
                    ->orWhere('type_of_requests.type_of_request_name', 'LIKE', "%{$search}%")
                    ->orWhere('suppliers.supplier_name', 'LIKE', "%{$search}%")
                    ->orWhere('major_categories.major_category_name', 'LIKE', "%{$search}%")
                    ->orWhere('minor_categories.minor_category_name', 'LIKE', "%{$search}%")
                    ->orWhere('divisions.division_name', 'LIKE', "%{$search}%")
                    ->orWhere('asset_statuses.asset_status_name', 'LIKE', "%{$search}%")
                    ->orWhere('cycle_count_statuses.cycle_count_status_name', 'LIKE', "%{$search}%")
                    ->orWhere('depreciation_statuses.depreciation_status_name', 'LIKE', "%{$search}%")
                    ->orWhere('movement_statuses.movement_status_name', 'LIKE', "%{$search}%")
                    ->orWhere('locations.location_name', 'LIKE', "%{$search}%")
                    ->orWhere('companies.company_name', 'LIKE', "%{$search}%")
                    ->orWhere('business_units.business_unit_name', 'LIKE', "%{$search}%")
                    ->orWhere('departments.department_name', 'LIKE', "%{$search}%")
                    ->orWhere('units.unit_name', 'LIKE', "%{$search}%")
                    ->orWhere('sub_units.sub_unit_name', 'LIKE', "%{$search}%")
                    ->orWhere('initial_debit.account_title_name', 'LIKE', "%{$search}%")
                    ->orWhere('initial_credit.account_title_name', 'LIKE', "%{$search}%")
                    ->orWhere('depreciation_debit.account_title_name', 'LIKE', "%{$search}%")
                    ->orWhere('depreciation_credit.account_title_name', 'LIKE', "%{$search}%");
//                    ->orWhere('account_titles.account_title_name', 'LIKE', "%{$search}%");
            });
        }

        if ($startDate && $endDate) {
            $additionalCost->whereDate('fixed_assets.created_at', '>=', $startDate)
                ->whereDate('fixed_assets.created_at', '<=', $endDate);
        }

        $additionalCost = $additionalCost->select(
            'additional_costs.id',
            'users.username as requester',
            'capexes.capex as capex',
            'capexes.project_name as project_name',
            'sub_capexes.sub_capex as sub_capex',
            'sub_capexes.sub_project as sub_project',
            'additional_costs.transaction_number',
            'additional_costs.reference_number',
            'additional_costs.pr_number',
            'additional_costs.po_number',
            'fixed_assets.vladimir_tag_number as vladimir_tag_number',
            'fixed_assets.tag_number',
            'fixed_assets.tag_number_old',
            'additional_costs.asset_description',
            'additional_costs.asset_specification',
            'type_of_requests.type_of_request_name as type_of_request',
            'suppliers.supplier_name',
            'additional_costs.accountability',
            'additional_costs.accountable',
            'additional_costs.received_by',
            'additional_costs.capitalized',
            'additional_costs.cellphone_number',
            'additional_costs.brand',
            'major_categories.major_category_name as major_category',
            'minor_categories.minor_category_name as minor_category',
            'divisions.division_name as division',
            'additional_costs.voucher',
            'additional_costs.voucher_date',
            'additional_costs.receipt',
            'additional_costs.quantity',
            'additional_costs.acquisition_date',
            'additional_costs.acquisition_cost',
            'fixed_assets.charged_department',
            'asset_statuses.asset_status_name as asset_status',
            'cycle_count_statuses.cycle_count_status_name as cycle_count_status',
            'depreciation_statuses.depreciation_status_name as depreciation_status',
            'movement_statuses.movement_status_name as movement_status',
            'fixed_assets.care_of',
            'companies.company_code as company_code',
            'companies.company_name as company_name',
            'business_units.business_unit_code as business_unit_code',
            'business_units.business_unit_name as business_unit_name',
            'departments.department_code as department_code',
            'departments.department_name as department_name',
            'units.unit_code as unit_code',
            'units.unit_name as unit_name',
            'sub_units.sub_unit_code as sub_unit_code',
            'sub_units.sub_unit_name as sub_unit_name',
            'locations.location_code as location_code',
            'locations.location_name as location_name',
            'minor_categories.accounting_entries_id as accounting_entries_id',

//            'account_titles.account_title_code as account_title_code',
//            'account_titles.account_title_name as account_title_name',
            'initial_debit.account_title_name as initial_debit_name',
            'initial_debit.account_title_code as initial_debit_code',
            'initial_credit.account_title_name as initial_credit_name',
            'initial_credit.account_title_code as initial_credit_code',
            'depreciation_debit.account_title_name as depreciation_debit_name',
            'depreciation_debit.account_title_code as depreciation_debit_code',
            'depreciation_credit.account_title_name as depreciation_credit_name',
            'depreciation_credit.account_title_code as depreciation_credit_code',

            'additional_costs.add_cost_sequence',
            'formulas.depreciation_method',
            'formulas.scrap_value',
            'major_categories.est_useful_life', // todo: subject to monitor for possible bug
            'formulas.depreciable_basis',
            'formulas.months_depreciated',
            'formulas.end_depreciation',
            'formulas.depreciation_per_year',
            'formulas.depreciation_per_month',
            'formulas.accumulated_cost',
            'formulas.remaining_book_value',
            'formulas.release_date',
            'formulas.start_depreciation',
//            'fixed_assets.created_at'
        );
        return $additionalCost;
    }
}