<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdditionalCostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Todo: Possible revision of the table structure
        Schema::create('additional_costs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('requester_id')->nullable();
            $table->unsignedInteger('fixed_asset_id');
            $table->unsignedInteger('supplier_id')->nullable();
            $table->foreign('fixed_asset_id')
                ->references('id')
                ->on('fixed_assets')
                ->onDelete('cascade');
            $table->string('pr_number')->nullable();
            $table->string('po_number')->nullable();
            $table->string('rr_number')->nullable();
//            $table->string('wh_number')->nullable();
            $table->unsignedInteger('warehouse_number')->nullable();
            $table->boolean('from_request')->default(0);
            $table->boolean('can_release')->default(0);
            $table->boolean('is_released')->default(0);
            $table->string('add_cost_sequence');
            $table->boolean('is_additional_cost')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('asset_description')->nullable();
            $table->unsignedInteger('type_of_request_id');
            $table->string('asset_specification')->nullable();
            $table->string('accountability');
            $table->string('accountable')->nullable();
            $table->string('capitalized')->default('Capitalized');
            $table->string('cellphone_number')->nullable();
            $table->string('brand')->nullable();
            $table->unsignedInteger('major_category_id')->nullable();
            $table->unsignedInteger('minor_category_id')->nullable();
            $table->string('voucher')->nullable();
            $table->date('voucher_date')->nullable();
            $table->string('receipt')->nullable();
            $table->string('quantity')->nullable();
            $table->string('depreciation_method')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->Biginteger('acquisition_cost')->nullable();
            $table->unsignedInteger('asset_status_id')->nullable();
            $table->unsignedInteger('cycle_count_status_id')->nullable();
            $table->unsignedInteger('depreciation_status_id')->nullable();
            $table->unsignedInteger('movement_status_id')->nullable();
            $table->string('care_of')->nullable();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('business_unit_id');
            $table->unsignedInteger('unit_id')->nullable();
            $table->unsignedInteger('subunit_id')->nullable();
            $table->unsignedInteger('department_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('account_id');
            $table->string('remarks')->nullable();
            $table->unsignedInteger('formula_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('unit_id')
                ->references('id')
                ->on('units')
                ->onDelete('cascade');
            $table->foreign('subunit_id')
                ->references('id')
                ->on('sub_units')
                ->onDelete('cascade');
            $table->foreign('warehouse_number')
                ->references('id')
                ->on('warehouse_numbers')
                ->onDelete('cascade');
            $table->foreign('requester_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');
            $table->foreign('type_of_request_id')
                ->references('id')
                ->on('type_of_requests')
                ->onDelete('cascade');
            $table->foreign('major_category_id')
                ->references('id')
                ->on('major_categories')
                ->onDelete('cascade');
            $table->foreign('minor_category_id')
                ->references('id')
                ->on('minor_categories')
                ->onDelete('cascade');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
            $table->foreign('business_unit_id')
                ->references('id')
                ->on('business_units')
                ->onDelete('cascade');
            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->onDelete('cascade');
            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->onDelete('cascade');
            $table->foreign('account_id')
                ->references('id')
                ->on('account_titles')
                ->onDelete('cascade');
            $table->foreign('asset_status_id')
                ->references('id')
                ->on('asset_statuses')
                ->onDelete('cascade');
            $table->foreign('cycle_count_status_id')
                ->references('id')
                ->on('cycle_count_statuses')
                ->onDelete('cascade');
            $table->foreign('depreciation_status_id')
                ->references('id')
                ->on('depreciation_statuses')
                ->onDelete('cascade');
            $table->foreign('movement_status_id')
                ->references('id')
                ->on('movement_statuses')
                ->onDelete('cascade');
            $table->foreign('formula_id')
                ->references('id')
                ->on('formulas')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('additional_costs');
    }
}
