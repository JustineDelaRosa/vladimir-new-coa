<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_approvals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('transaction_number');
            $table->unsignedInteger('approver_id');
            $table->unsignedInteger('requester_id');
            $table->Integer('layer');
            $table->string('status')->nullable();
            $table->timestamps();

//            $table->foreign('asset_request_id')->references('id')->on('asset_requests');
            $table->foreign('approver_id')->references('id')->on('approvers');
            $table->foreign('requester_id')->references('id')->on('users');
            $table->foreign('transaction_number')->references('transaction_number')->on('asset_requests');
            $table->unique(['transaction_number', 'approver_id', 'requester_id', 'layer'], 'asset_approvals_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_approvals');
    }
}
