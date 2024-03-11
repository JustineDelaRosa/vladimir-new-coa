<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApproverLayersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('approver_layers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('requester_id');
            $table->unsignedInteger('approver_id');
            $table->Integer('layer')->default(1);
//            $table->boolean('is_active')->default(true);
//            $table->softDeletes();
            $table->timestamps();

            $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('approvers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('approver_layers');
    }
}
