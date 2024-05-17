<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransferApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_approvals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('transfer_number');
            $table->unsignedInteger('approver_id');
            $table->unsignedInteger('requester_id');
            $table->Integer('layer');
            $table->string('status')->nullable();
            $table->timestamps();

//            $table->foreign('asset_request_id')->references('id')->on('asset_requests');
            $table->foreign('approver_id')->references('id')->on('approvers');
            $table->foreign('requester_id')->references('id')->on('users');
            $table->foreign('transfer_number')->references('transfer_number')->on('asset_transfer_requests');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trasfer_approvals');
    }
}
