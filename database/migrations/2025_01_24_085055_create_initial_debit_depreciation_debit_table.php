<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInitialDebitDepreciationDebitTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('initial_debit_depreciation_debit', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('initial_debit_id');
            $table->unsignedInteger('depreciation_debit_id');
            $table->timestamps();
            $table->unique(['initial_debit_id', 'depreciation_debit_id'], 'initial_debit_depreciation_debit_unique');

            $table->foreign('initial_debit_id')->references('sync_id')->on('account_titles');
            $table->foreign('depreciation_debit_id')->references('sync_id')->on('account_titles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('initial_debit_depreciation_debit');
    }
}
