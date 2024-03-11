<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUnitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('units', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sync_id')->unique()->index();
            $table->string('unit_code');
            $table->string('unit_name');
            $table->boolean('is_active');
            $table->unsignedInteger('department_sync_id');
            $table->timestamps();
            $table->foreign('department_sync_id')
                ->references('sync_id')
                ->on('departments')
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
        Schema::dropIfExists('units');
    }
}
