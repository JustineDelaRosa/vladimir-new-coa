<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('requester_id');
            $table->string('status')->default('For Approval by Approver 1');
//            $table->string('remarks')->nullable();
            $table->unsignedInteger('type_of_request_id');
            $table->unsignedInteger('capex_id')->nullable();
            $table->unsignedInteger('sub_capex_id')->nullable();
            $table->string('vladimir_tag_number')->nullable();
            $table->string('asset_description');
            $table->string('asset_specification');
            $table->enum('accountability', ['Personal Issued', 'Common']);
            $table->string('accountable')->nullable();
            $table->string('cellphone_number');
            $table->string('capitalized')->default('Capitalized');
            $table->string('brand');
            $table->string('quantity')->nullable();
            $table->unsignedInteger('division_id')->nullable();
            $table->unsignedInteger('major_category_id')->nullable();
            $table->unsignedInteger('minor_category_id')->nullable();
            $table->string('voucher')->nullable();
            $table->string('receipt')->nullable();
            $table->enum('depreciation_method',['STL', 'One Time'])->nullable();
            $table->string('care_of')->nullable();
            //STATUSES
            $table->unsignedInteger('asset_status_id')->nullable();
            $table->unsignedInteger('cycle_count_status_id')->nullable();
            $table->unsignedInteger('depreciation_status_id')->nullable();
            $table->unsignedInteger('movement_status_id')->nullable();
            //DATES
            $table->date('voucher_date')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->date('release_date')->nullable();
            $table->date('start_depreciation')->nullable();
            $table->double('acquisition_cost')->nullable();
            $table->double('scrap_value')->nullable();
            $table->double('depreciable_basis')->nullable();
            //COA
//            $table->unsignedInteger('company_id')->nullable();
//            $table->unsignedInteger('department_id')->nullable();
//            $table->unsignedInteger('location_id')->nullable();
//            $table->unsignedInteger('account_id')->nullable();
            $table->string('company_code')->nullable();
            $table->string('company')->nullable();
            $table->string('department_code')->nullable();
            $table->string('department')->nullable();
            $table->string('location_code')->nullable();
            $table->string('location')->nullable();
            $table->string('account_title_code')->nullable();
            $table->string('account_title')->nullable();

            $table->foreign('requester_id')->references('id')->on('users');
            $table->foreign('type_of_request_id')->references('id')->on('type_of_requests');
            $table->foreign('sub_capex_id')->references('id')->on('sub_capexes');
            $table->foreign('capex_id')->references('id')->on('capexes');
            $table->foreign('division_id')->references('id')->on('divisions');
            $table->foreign('major_category_id')->references('id')->on('major_categories');
            $table->foreign('minor_category_id')->references('id')->on('minor_categories');
            $table->foreign('asset_status_id')->references('id')->on('asset_statuses');
            $table->foreign('cycle_count_status_id')->references('id')->on('cycle_count_statuses');
            $table->foreign('depreciation_status_id')->references('id')->on('depreciation_statuses');
            $table->foreign('movement_status_id')->references('id')->on('movement_statuses');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_requests');
    }
}
