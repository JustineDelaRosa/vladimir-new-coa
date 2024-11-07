<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehousesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sync_id')->unique();
            $table->string('warehouse_name');
            $table->string('warehouse_code')->unique();
//            $table->unsignedInteger('location_id')->index();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

//            $table->foreign('location_id')->references('id')->on('locations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('warehouses');
    }
}
