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
            $table->timestamps();
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
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
