<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormulasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('formulas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('fixed_asset_id');
            $table->string('depreciation_method');
            $table->integer('est_useful_life');
            $table->date('acquisition_date');
            $table->bigInteger('acquisition_cost');
            $table->integer('scrap_value');
            $table->bigInteger('original_cost');
            $table->bigInteger('accumulated_cost');
            $table->integer('age'); //months
            $table->string('end_depreciation'); //date format yyyy-mm
            $table->BigInteger('depreciation_per_year');
            $table->BigInteger('depreciation_per_month');
            $table->BigInteger('remaining_book_value');
            $table->year('start_depreciation'); //year
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('fixed_asset_id')
                ->references('id')
                ->on('fixed_assets')
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
        Schema::dropIfExists('formulas');
    }
}
