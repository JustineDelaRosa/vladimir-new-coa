<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepreciationHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('depreciation_histories', function (Blueprint $table) {
            $table->increments('íd');
            $table->unsignedInteger('fixed_asset_id');
            $table->string('depreciated_date');
            $table->double('depreciation_per_month');
            $table->double('depreciation_per_year');
            $table->double('accumulated_cost');
            $table->double('remaining_book_value');
            $table->double('depreciation_basis');
            $table->double('acquisition_cost');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('business_unit_id');
            $table->unsignedInteger('department_id');
            $table->unsignedInteger('unit_id');
            $table->unsignedInteger('subunit_id');
            $table->unsignedInteger('location_id');
            $table->timestamps();
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->foreign('subunit_id')->references('id')->on('sub_units')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('depreciation_histories');
    }
}
