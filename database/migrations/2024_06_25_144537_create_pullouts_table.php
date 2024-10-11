<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePulloutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pullouts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('movement_id');
            $table->string('care_of');
            $table->timestamps();
            $table->foreign('movement_id')
                ->references('id')
                ->on('movement_numbers')
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
        Schema::dropIfExists('pullouts');
    }
}
