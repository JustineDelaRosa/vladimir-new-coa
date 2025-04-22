<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetSmallToolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_small_tools', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('fixed_asset_id');
            $table->text('description'); //sync_id of items
            $table->text('specification')->nullable();
            $table->string('receiver')->nullable();
//            $table->unsignedInteger('receiving_warehouse_id')->nullable();
            $table->integer('quantity');
            $table->string('pr_number')->nullable();
            $table->string('po_number')->nullable();
            $table->string('rr_number')->nullable();
            $table->string('status_description')->nullable();
            $table->decimal('acquisition_cost', 10, 2)->default(0);
            $table->boolean('is_active')->default(1);
//            $table->boolean('to_release')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets');
//            $table->foreign('receiving_warehouse_id')->references('sync_id')->on('warehouses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_small_tools');
    }
}
