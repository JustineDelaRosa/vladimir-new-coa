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
            $table->unsignedInteger('fixed_asset_id');
            $table->foreign('fixed_asset_id')
                ->references('id')
                ->on('fixed_assets')
                ->onDelete('cascade');
            $table->boolean('is_additional_cost')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('asset_description');
            $table->unsignedInteger('type_of_request_id');
            $table->string('asset_specification');
            $table->string('accountability');
            $table->string('accountable')->nullable();
            $table->string('capitalized')->default('Capitalized');
            $table->string('cellphone_number');
            $table->string('brand');
            $table->unsignedInteger('major_category_id');
            $table->unsignedInteger('minor_category_id');
            $table->string('voucher');
            $table->string('receipt');
            $table->string('quantity');
            $table->string('depreciation_method');
            $table->date('acquisition_date');
            $table->Biginteger('acquisition_cost');
            $table->unsignedInteger('asset_status_id');
            $table->unsignedInteger('cycle_count_status_id');
            $table->unsignedInteger('depreciation_status_id');
            $table->unsignedInteger('movement_status_id');
            $table->string('care_of');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('department_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('account_id');
            $table->string('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();
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

