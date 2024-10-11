<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMovementApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('movement_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('movement_number_id')->index();
            $table->unsignedInteger('approver_id')->index();
            $table->unsignedInteger('requester_id')->index();
            $table->integer('layer');
            $table->string('status')->nullable();
            $table->timestamps();

            $table->foreign('movement_number_id')
                ->references('id')
                ->on('movement_numbers')
                ->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('approvers');
            $table->foreign('requester_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('movement_approvals');
    }
}
