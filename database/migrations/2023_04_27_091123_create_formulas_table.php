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
            $table->string('depreciation_method')->nullable();
            //            $table->decimal('est_useful_life' , 10, 1);
            $table->date('acquisition_date')->nullable();
            $table->double('acquisition_cost')->nullable();
            $table->double('scrap_value')->nullable();
            $table->double('depreciable_basis')->nullable();
            $table->double('accumulated_cost')->nullable();
            $table->integer('months_depreciated')->nullable(); //months
            $table->string('end_depreciation')->nullable(); //date format yyyy-mm
            $table->double('depreciation_per_year')->nullable();
            $table->double('depreciation_per_month')->nullable();
            $table->double('remaining_book_value')->nullable();
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
