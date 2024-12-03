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
            $table->unsignedInteger('fixed_asset_id');
            $table->string('description');
            $table->string('care_of');
            $table->string('remarks')->nullable();
            $table->enum('evaluation', ['Repaired', 'For Disposal'])->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('fixed_asset_id')
                ->references('id')
                ->on('fixed_assets')
                ->onDelete('cascade');
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
