<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoordinatorHandlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coordinator_handles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('business_unit_id');
            $table->unsignedInteger('department_id');
            $table->unsignedInteger('unit_id');
            $table->unsignedInteger('subunit_id');
            $table->unsignedInteger('location_id');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('business_unit_id')->references('id')->on('business_units');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('unit_id')->references('id')->on('units');
            $table->foreign('subunit_id')->references('id')->on('sub_units');
            $table->foreign('location_id')->references('id')->on('locations');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coordinator_handles');
    }
}
