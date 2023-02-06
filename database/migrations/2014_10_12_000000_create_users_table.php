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
            $table->string('first_name');
            $table->string('middle_name');
            $table->string('last_name');
            $table->string('department');
            $table->string('position');
            $table->string('username');
            $table->string('password');
            $table->boolean('is_active');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes($column='deleted_at',$precision=0);
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
