<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOneChargingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('one_chargings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sync_id');
            $table->string('code');
            $table->string('name');
            $table->integer('company_id');
            $table->string('company_code');
            $table->string('company_name');
            $table->integer('business_unit_id');
            $table->string('business_unit_code');
            $table->string('business_unit_name');
            $table->integer('department_id');
            $table->string('department_code');
            $table->string('department_name');
            $table->integer('unit_id');
            $table->string('unit_code');
            $table->string('unit_name');
            $table->integer('subunit_id');
            $table->string('subunit_code');
            $table->string('subunit_name');
            $table->integer('location_id');
            $table->string('location_code');
            $table->string('location_name');
            $table->unsignedInteger('receiving_warehouse_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('receiving_warehouse_id')->references('sync_id')->on('warehouses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('one_chargings');
    }
}
