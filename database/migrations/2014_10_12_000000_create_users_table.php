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
            $table->string('department_name');
            $table->string('subunit_name');
            $table->boolean('is_active');
            $table->unsignedInteger('role_id');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes($column='deleted_at',$precision=0);
            $table->foreign('role_id')
            ->references('id')
            ->on('role_management')
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
        Schema::dropIfExists('users');
    }
}
