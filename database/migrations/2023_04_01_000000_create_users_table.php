<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_id');
            $table->string('firstname');
            $table->string('lastname');
            $table->string('username');
            $table->string('password');
            $table->unsignedInteger('warehouse_id')->nullable();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('business_unit_id');
            $table->unsignedInteger('department_id');
            $table->unsignedInteger('unit_id')->nullable();
            $table->unsignedInteger('subunit_id')->nullable();
            $table->unsignedInteger('location_id');
            $table->boolean('is_active');
            $table->unsignedInteger('role_id');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes($column='deleted_at',$precision=0);
            $table->foreign('role_id')
            ->references('id')
            ->on('role_management');
            $table->foreign('company_id')
            ->references('id')
            ->on('companies');
            $table->foreign('business_unit_id')
            ->references('id')
            ->on('business_units');
            $table->foreign('department_id')
            ->references('id')
            ->on('departments');
            $table->foreign('unit_id')
            ->references('id')
            ->on('units');
            $table->foreign('subunit_id')
            ->references('id')
            ->on('sub_units');
            $table->foreign('location_id')
            ->references('id')
            ->on('locations');
            $table->foreign('warehouse_id')
            ->references('id')
            ->on('warehouses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
