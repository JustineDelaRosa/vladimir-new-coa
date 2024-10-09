<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmallToolsItemTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('small_tools_item', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('small_tool_sync_id');
            $table->unsignedInteger('item_sync_id');
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->foreign('small_tool_sync_id')
                ->references('sync_id')
                ->on('small_tools')
                ->onDelete('cascade');
            $table->foreign('item_sync_id')
                ->references('sync_id')
                ->on('items')
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
        Schema::dropIfExists('small_tools_item');
    }
}
