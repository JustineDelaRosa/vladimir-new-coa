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
            $table->unsignedInteger('major_category_id');
            $table->unsignedInteger('accounting_entries_id');
            $table->string('minor_category_name');
            $table->boolean('is_active');
            $table->timestamp('created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(\DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
            $table->softDeletes($column = 'deleted_at', $precision = 0);
            $table->foreign('major_category_id')
                ->references('id')
                ->on('major_categories')
                ->onDelete('cascade');
            $table->foreign('accounting_entries_id')
                ->references('id')
                ->on('accounting_entries')
                ->onDelete('cascade');
            $table->unique(['major_category_id', 'minor_category_name', 'account_title_sync_id'], 'major_minor_account_unique');
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
