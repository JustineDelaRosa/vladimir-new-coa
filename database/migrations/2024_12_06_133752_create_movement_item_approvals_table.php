<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMovementItemApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('movement_item_approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('item', 'item_id');
            $table->unsignedInteger('approver_id');
            $table->integer('layer');
            $table->string('status');
            $table->timestamps();

            $table->foreign('approver_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('movement_item_approvals');
    }
}
