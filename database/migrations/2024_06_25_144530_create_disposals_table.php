<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDisposalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('disposals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('movement_id');
            $table->string('status');
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
        Schema::dropIfExists('disposals');
    }
}
