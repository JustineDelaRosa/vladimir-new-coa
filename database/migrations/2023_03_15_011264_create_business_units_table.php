<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessUnitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_sync_id');
            $table->unsignedInteger('sync_id')->unique()->index();
            $table->string('business_unit_code');
            $table->string('business_unit_name');
            $table->boolean('is_active');
            $table->timestamps();

            $table->foreign('company_sync_id')
                ->references('sync_id')
                ->on('companies')
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
        Schema::dropIfExists('business_units');
    }
}
