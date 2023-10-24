<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sync_id')->unique()->index();
            $table->unsignedInteger('company_sync_id');
            $table->foreign('company_sync_id')
                ->references('sync_id')
                ->on('companies')
                ->onDelete('cascade');
            $table->unsignedInteger('division_id')->nullable();
            $table->foreign('division_id')
                ->references('id')
                ->on('divisions')
                ->onDelete('cascade');
//            $table->unsignedInteger('location_sync_id')->nullable();
//            $table->foreign('location_sync_id')
//                ->references('sync_id')
//                ->on('locations')
//                ->onDelete('cascade');
            $table->string('department_code')->unique();
            $table->longText('department_name');
            $table->boolean('is_active');
            $table->timestamp('created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(\DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('departments');
    }
}
