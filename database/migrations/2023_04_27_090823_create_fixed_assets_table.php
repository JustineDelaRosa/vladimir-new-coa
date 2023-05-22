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
            $table->string('capex')->nullable();
            $table->string('project_name');
            $table->string('vladimir_tag_number');
            $table->string('tag_number');
            $table->string('tag_number_old');
            $table->string('asset_description');
            $table->string('type_of_request');
            $table->string('asset_specification');
            $table->string('accountability');
            $table->string('accountable');
            $table->string('cellphone_number');
            $table->string('brand');
            $table->unsignedInteger('major_category_id');
            $table->unsignedInteger('minor_category_id');
            $table->unsignedInteger('division_id');
            $table->string('voucher');
            $table->string('receipt');
            $table->string('quantity');
            $table->string('depreciation_method');
            $table->integer('est_useful_life');
            $table->date('acquisition_date');
            $table->Biginteger('acquisition_cost');
            $table->boolean('is_active'); //->default(1)
            $table->boolean('is_old_asset')->default(0);
            $table->string('care_of');
            $table->unsignedInteger('company_id');
            $table->string('company_name');
            $table->unsignedInteger('department_id');
            $table->string('department_name');
            $table->unsignedInteger('location_id');
            $table->string('location_name');
            $table->unsignedInteger('account_id');
            $table->string('account_title');
            $table->softDeletes();
            $table->timestamps();
            // $table->foreign('type_of_request_id')
            //     ->references('id')
            //     ->on('type_of_requests')
            //     ->onDelete('cascade');
            $table->foreign('major_category_id')
                ->references('id')
                ->on('major_categories')
                ->onDelete('cascade');
            $table->foreign('minor_category_id')
                ->references('id')
                ->on('minor_categories')
                ->onDelete('cascade');
            $table->foreign('division_id')
                ->references('id')
                ->on('divisions')
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
