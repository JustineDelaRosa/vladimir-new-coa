<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmallToolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('small_tools', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sync_id')->unique()->index();;
            $table->string('small_tool_code');
            $table->string('small_tool_name');
            $table->unsignedInteger('uom_id');
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('small_tools');
    }
}
