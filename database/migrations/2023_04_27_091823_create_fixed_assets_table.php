<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFixedAssetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('capex_id')->nullable();
//            $table->string('project_name');
            $table->unsignedInteger('sub_capex_id')->nullable();
//            $table->string('sub_project');
            $table->string('vladimir_tag_number');
            $table->string('tag_number');
            $table->string('tag_number_old');
            $table->string('asset_description');
            $table->unsignedInteger('type_of_request_id');
            $table->string('charged_department');
            $table->string('asset_specification');
            $table->string('accountability');
            $table->string('accountable')->nullable();
            $table->string('capitalized')->default('Capitalized');
            $table->string('cellphone_number');
            $table->string('brand');
            $table->unsignedInteger('major_category_id');
            $table->unsignedInteger('minor_category_id');
            $table->string('voucher');
            $table->date('voucher_date')->nullable();
            $table->string('receipt');
            $table->string('quantity');
            $table->string('depreciation_method');
//            $table->decimal('est_useful_life',18,1);
            $table->date('acquisition_date');
            $table->double('acquisition_cost');
            $table->unsignedInteger('asset_status_id');
            $table->unsignedInteger('cycle_count_status_id');
            $table->unsignedInteger('depreciation_status_id');
            $table->unsignedInteger('movement_status_id');
            $table->boolean('is_old_asset')->default(false);
            $table->boolean('is_additional_cost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('care_of');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('department_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('account_id');
            $table->string('remarks')->nullable();
            $table->integer('print_count')->default(0);
            $table->timestamp('last_printed')->nullable();
            $table->unsignedInteger('formula_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('capex_id')
                ->references('id')
                ->on('capexes')
                ->onDelete('cascade');
            $table->foreign('sub_capex_id')
                ->references('id')
                ->on('sub_capexes')
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
        Schema::dropIfExists('fixed_assets');
    }
}