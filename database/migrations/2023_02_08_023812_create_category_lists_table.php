<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_lists', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('service_provider_id');
            $table->unsignedInteger('major_category_id');
            $table->unsignedInteger('minor_category_id');
            $table->boolean('is_active');
            $table->timestamp('created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(\DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
            $table->softDeletes($column='deleted_at',$precision=0);
            $table->foreign('service_provider_id')
            ->references('id')
            ->on('service_providers')
            ->onDelete('cascade');
            $table->foreign('major_category_id')
            ->references('id')
            ->on('major_categories')
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
        Schema::dropIfExists('category_lists');
    }
}
