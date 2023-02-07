<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMinorCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('minor_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('minor_category_name');
            $table->enum('urgency_level', ['LOW', 'MEDIUM', 'HIGH']);
            $table->boolean('personally_assign');
            $table->boolean('evaluate_in_every_movement');
            $table->boolean('is_active');
            $table->timestamp('created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(\DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
            $table->softDeletes($column='deleted_at',$precision=0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('minor_categories');
    }
}
