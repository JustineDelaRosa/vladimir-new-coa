<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetTransferContainersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_transfer_containers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('created_by_id');
            $table->unsignedInteger('fixed_asset_id');
            $table->string('status')->default('For Approval of Approver 1');
            $table->string('accountability');
            $table->string('accountable')->nullable();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('business_unit_id');
            $table->unsignedInteger('department_id');
            $table->unsignedInteger('unit_id')->nullable();
            $table->unsignedInteger('subunit_id')->nullable();
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('account_id');
            $table->string('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('business_unit_id')
                ->references('id')
                ->on('business_units')
                ->onDelete('cascade');
            $table->foreign('unit_id')
                ->references('id')
                ->on('units')
                ->onDelete('cascade');
            $table->foreign('subunit_id')
                ->references('id')
                ->on('sub_units')
                ->onDelete('cascade');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->onDelete('cascade');
            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->onDelete('cascade');
            $table->foreign('account_id')
                ->references('id')
                ->on('account_titles')
                ->onDelete('cascade');
            $table
                ->foreign('created_by_id')
                ->references('id')
                ->on('users');
            $table->foreign('fixed_asset_id')
                ->references('id')
                ->on('fixed_assets')
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
        Schema::dropIfExists('asset_transfer_containers');
    }
}
