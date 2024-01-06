<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_requests', function (Blueprint $table) {
            //REQUEST FORM
            $table->increments('id');
            $table->unsignedInteger('requester_id');
            $table->string('status')->default('For Approval of Approver 1');
            $table
                ->string('transaction_number')
                ->index()
                ->nullable();
            $table->string('reference_number');
            $table->string('pr_number')->nullable();
            $table->string('po_number')->nullable();
            $table->string('rr_number')->nullable();
            $table->boolean('is_add_cost')->default(0);
            $table->unsignedInteger('fixed_asset_id')->nullable();
            $table->string('additional_info')->nullable();
            $table->string('acquisition_details');
            $table->boolean('is_resubmit')->default(0);
            $table->date('delivery_date')->nullable();
            $table->double('unit_price')->nullable();
            $table->unsignedInteger('supplier_id')->nullable();

            //TO BE FILL UP BY THE REQUESTER
            $table->string('remarks')->nullable();
            $table->unsignedInteger('type_of_request_id');
            //            $table->UnsignedInteger('charged_department_id');
            $table->unsignedInteger('subunit_id'); //For ChargedDepartment
            $table->enum('accountability', ['Personal Issued', 'Common']);
            $table->string('accountable')->nullable();
            $table->string('asset_description');
            $table->string('asset_specification')->nullable();
            $table->string('cellphone_number')->nullable();
            $table->string('brand');
            $table->string('quantity')->nullable();
            $table->integer('quantity_delivered')->nullable;

            //ATTACHMENT TYPE
            $table->enum('attachment_type', ['Budgeted', 'Unbudgeted']);
            //ATTACHMENTS
            //            $table->text('letter_of_request')->nullable();
            //            $table->text('quotation')->nullable();
            //            $table->text('specification_form')->nullable();
            //            $table->text('tool_of_trade')->nullable();
            //            $table->json('other_attachments')->nullable();

            //ADDITIONAL FIELDS TO BE ADDED
            //            $table->unsignedInteger('capex_id')->nullable();
            //            $table->unsignedInteger('sub_capex_id')->nullable();
            $table->string('vladimir_tag_number')->nullable();

            $table->string('capitalized')->default('Capitalized');

            $table->unsignedInteger('division_id')->nullable();
            $table->unsignedInteger('major_category_id')->nullable();
            $table->unsignedInteger('minor_category_id')->nullable();
            $table->string('voucher')->nullable();
            $table->string('receipt')->nullable();
            $table->enum('depreciation_method', ['STL', 'One Time'])->nullable();
            $table->string('care_of')->nullable();
            //STATUSES
            $table->unsignedInteger('asset_status_id')->nullable();
            $table->unsignedInteger('cycle_count_status_id')->nullable();
            $table->unsignedInteger('depreciation_status_id')->nullable();
            $table->unsignedInteger('movement_status_id')->nullable();
            //DATES
            $table->date('voucher_date')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->date('release_date')->nullable();
            $table->date('start_depreciation')->nullable();
            $table->double('acquisition_cost')->nullable();
            $table->double('scrap_value')->nullable();
            $table->double('depreciable_basis')->nullable();
            //COA
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('account_title_id')->nullable();
            $table->unsignedInteger('business_unit_id')->nullable(); //TODO: For the meantime
            $table->integer('print_count')->default(0);
            $table->softDeletes();
            $table->timestamp('last_printed')->nullable();

            $table
                ->foreign('requester_id')
                ->references('id')
                ->on('users');
            $table
                ->foreign('type_of_request_id')
                ->references('id')
                ->on('type_of_requests');
            //            $table->foreign('charged_department_id')->references('id')->on('departments');
            $table
                ->foreign('subunit_id')
                ->references('id')
                ->on('sub_units');
            $table
                ->foreign('company_id')
                ->references('id')
                ->on('companies');
            $table
                ->foreign('department_id')
                ->references('id')
                ->on('departments');
            $table
                ->foreign('location_id')
                ->references('id')
                ->on('locations');
            $table
                ->foreign('account_title_id')
                ->references('id')
                ->on('account_titles');

            //            $table->foreign('sub_capex_id')->references('id')->on('sub_capexes');
            //            $table->foreign('capex_id')->references('id')->on('capexes');
            $table
                ->foreign('division_id')
                ->references('id')
                ->on('divisions');
            $table
                ->foreign('major_category_id')
                ->references('id')
                ->on('major_categories');
            $table
                ->foreign('minor_category_id')
                ->references('id')
                ->on('minor_categories');
            $table
                ->foreign('asset_status_id')
                ->references('id')
                ->on('asset_statuses');
            $table
                ->foreign('cycle_count_status_id')
                ->references('id')
                ->on('cycle_count_statuses');
            $table
                ->foreign('depreciation_status_id')
                ->references('id')
                ->on('depreciation_statuses');
            $table
                ->foreign('movement_status_id')
                ->references('id')
                ->on('movement_statuses');
            $table
                ->foreign('business_unit_id')
                ->references('id')
                ->on('companies');
            $table
                ->foreign('supplier_id')
                ->references('id')
                ->on('suppliers');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_requests');
    }
}
