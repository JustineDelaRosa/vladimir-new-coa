<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountingEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->increments('id');
//            $table->string('acc_entry_type');
            $table->unsignedInteger('initial_debit');
            $table->unsignedInteger('initial_credit')->nullable();
            $table->unsignedInteger('depreciation_debit')->nullable();
            $table->unsignedInteger('depreciation_credit');
            $table->timestamps();

            $table->foreign('initial_debit')
                ->references('sync_id')
                ->on('account_titles')
                ->onDelete('cascade');
            $table->foreign('initial_credit')
                ->references('sync_id')
                ->on('account_titles')
                ->onDelete('cascade');
            $table->foreign('depreciation_debit')
                ->references('sync_id')
                ->on('account_titles')
                ->onDelete('cascade');
            $table->foreign('depreciation_credit')
                ->references('sync_id')
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
        Schema::dropIfExists('accounting_entries');
    }
}
