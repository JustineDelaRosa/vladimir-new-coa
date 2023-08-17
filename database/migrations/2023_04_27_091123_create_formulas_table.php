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
            $table->string('depreciation_method');
//            $table->decimal('est_useful_life' , 10, 1);
            $table->date('acquisition_date');
            $table->bigInteger('acquisition_cost');
            $table->integer('scrap_value');
            $table->bigInteger('depreciable_basis');
            $table->bigInteger('accumulated_cost');
            $table->integer('months_depreciated'); //months
            $table->string('end_depreciation')->nullable(); //date format yyyy-mm
            $table->BigInteger('depreciation_per_year');
            $table->BigInteger('depreciation_per_month');
            $table->BigInteger('remaining_book_value');
            $table->string('release_date')->nullable(); //date format yyyy-mm-dd
            $table->string('start_depreciation')->nullable(); //date format yyyy-mm
            $table->softDeletes();
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
        Schema::dropIfExists('formulas');
    }
}
