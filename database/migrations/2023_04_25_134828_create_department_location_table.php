<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentLocationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('department_location', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('department_sync_id');
            $table->unsignedInteger('location_sync_id');

            $table->foreign('department_sync_id')
                ->references('sync_id')
                ->on('departments')
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
        Schema::dropIfExists('department_location');
    }
}
