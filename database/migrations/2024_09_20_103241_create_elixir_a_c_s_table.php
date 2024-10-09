<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateElixirACSTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('elixir_a_c_s', function (Blueprint $table) {
            $table->increments('íd');
            $table->unsignedInteger('sync_id')->nullable();
            $table->boolean('is_tagged')->default(false);
            $table->string('po_number')->nullable();
            $table->string('pr_number')->nullable();
            $table->string('mir_id')->nullable();
            $table->string('warehouse_id')->nullable();
            $table->string('acquisition_date')->nullable();
            $table->string('customer_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('item_code')->nullable();
            $table->string('item_description')->nullable();
            $table->string('uom')->nullable();
            $table->string('served_quantity')->nullable();
            $table->string('asset_tag')->nullable();
            $table->string('approved_date')->nullable();

            $table->string('released_date')->nullable();
            $table->string('unit_price')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('business_unit_id')->nullable();
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('unit_id')->nullable();
            $table->unsignedInteger('sub_unit_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
/*            $table->unsignedInteger('major_category_id')->nullable();
            $table->unsignedInteger('minor_category_id')->nullable();*/
            $table->string('major_category_name')->nullable();
            $table->string('minor_category_name')->nullable();

            $table->timestamps();

//            $table->foreign('company_id')->references('id')->on('companies');
//            $table->foreign('business_unit_id')->references('id')->on('business_units');
//            $table->foreign('department_id')->references('id')->on('departments');
//            $table->foreign('unit_id')->references('id')->on('units');
//            $table->foreign('sub_unit_id')->references('id')->on('sub_units');
//            $table->foreign('location_id')->references('id')->on('locations');
//            $table->foreign('major_category_id')->references('id')->on('major_categories');
//            $table->foreign('minor_category_id')->references('id')->on('minor_categories');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('elixir_a_c_s');
    }
}
