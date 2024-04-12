<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetDisposalApproversTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_disposal_approvers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('approver_id');
            $table->unsignedInteger('unit_id');
            $table->unsignedInteger('subunit_id');
            $table->string('layer')->default('1');
            $table->timestamps();
            $table->foreign('approver_id')->references('id')->on('approvers')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->foreign('subunit_id')->references('id')->on('sub_units')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_disposal_approvers');
    }
}
