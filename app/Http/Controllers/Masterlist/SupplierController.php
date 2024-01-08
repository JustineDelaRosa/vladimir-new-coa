<?php

namespace App\Http\Controllers\Masterlist;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
use App\Http\Requests\Supplier\CreateSupplierRequest;
use App\Models\Supplier;

class SupplierController extends Controller
{

    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $supplierStatus = $request->status ?? 'active';
        $isActiveStatus = ($supplierStatus === 'deactivated') ? 0 : 1;

        $supplier = Supplier::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();

        return $supplier;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $supplierData = $request->input('result.suppliers');
        foreach ($supplierData as $suppliers) {
            $sync_id = $suppliers['id'];
            $supplier_code = $suppliers['code'];
            $supplier_name = $suppliers['name'];
            $is_active = $suppliers['status'];

            $sync = Supplier::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'supplier_code' => $supplier_code,
                    'supplier_name' => $supplier_name,
                    'is_active' => $is_active
                ],
            );
        }
        return $this->responseSuccess('Successfully Synced!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
