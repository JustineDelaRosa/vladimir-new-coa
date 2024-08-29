<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubunitLocationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subunit_location', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('subunit_sync_id');
            $table->unsignedInteger('location_sync_id');

            $table->foreign('subunit_sync_id')
                ->references('sync_id')
                ->on('sub_units')
                ->onDelete('cascade');
            $table->foreign('location_sync_id')
                ->references('sync_id')
                ->on('locations')
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
        Schema::dropIfExists('subunit_location');
    }
}
