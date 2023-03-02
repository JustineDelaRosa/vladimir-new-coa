<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryListTagMinorCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_list_tag_minor_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_list_id');
            $table->unsignedInteger('minor_category_id');
            $table->boolean('is_active');
            $table->timestamp('created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(\DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
            $table->foreign('category_list_id')
            ->references('id')
            ->on('category_lists')
            ->onDelete('cascade');
            $table->foreign('minor_category_id')
            ->references('id')
            ->on('minor_categories')
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
        Schema::dropIfExists('category_list_tag_minor_categories');
    }
}
