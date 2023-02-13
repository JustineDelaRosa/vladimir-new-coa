<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Requests\Supplier\SupplierRequest;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $Supplier = Supplier::get();
        return $Supplier;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SupplierRequest $request)
    {
        $supplier_name = $request->supplier_name;
        $address = $request->address;
        $contact_no = $request->contact_no;
        $Supplier = Supplier::query();
        // if($Supplier->where('service_name')->exists()){
        //     return response()->json(['message' => 'Supplier already Exists!'], 409);
        // }
        $create=$Supplier->create([
            'supplier_name' => $supplier_name,
            'address' => $address,
            'contact_no' => $contact_no,
            'is_active' => 1
        ]);
        return response()->json(['message' => 'Successfully Created!', 'data' => $create]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
       $Supplier = Supplier::query();
       if(!$Supplier->where('id', $id)->exists()){
        return response()->json(['error' => 'Supplier Route Not Found'], 404);
       }
       return $Supplier->where('id', $id)->first();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(SupplierRequest $request, $id)
    {
        $supplier_name = $request->supplier_name;
        $address = $request->address;
        $contact_no = $request->contact_no;
        if(!Supplier::where('id', $id)->exists()){
            return response()->json(['error' => 'Supplier Route Not Found'], 404);
        }
        if(Supplier::where('id',$id)->where([
            'supplier_name' => $supplier_name,
            'address' => $address,
            'contact_no' => $contact_no
        ])->exists()){
            return response()->json(['message' => 'No Changes'], 200);
        }
        $update = Supplier::where('id', $id)->update([
            'supplier_name' => $supplier_name,
            'address' => $address,
            'contact_no' => $contact_no
        ]);
        return response()->json(['message' => 'Successfully Updated!', 'data' => Supplier::where('id',$id)->first()], 200);
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

    public function archived(SupplierRequest $request, $id){

        $status = $request->status; 
        $Supplier = Supplier::query();
        if(!$Supplier->withTrashed()->where('id', $id)->exists()){
            return response()->json(['error' => 'Supplier Route Not Found'], 404);
        } 

        if($status == false){
            if(!Supplier::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{
                $updateStatus = $Supplier->where('id', $id)->update(['is_active' => false]);
                $Supplier->where('id',$id)->delete();
                return response()->json(['message' => 'Supplier Successfully Deactived!'], 200);
            }
        }
        if($status == true){
            if(Supplier::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{              
                $restoreUser = $Supplier->withTrashed()->where('id',$id)->restore();
                $updateStatus = $Supplier->update(['is_active' => true]); 
                return response()->json(['message' => 'Supplier Successfully Activated!'], 200);

            }

        }

    }

    public function search(Request $request){
        $search = $request->query('search');
        $limit = $request->query('limit');
        $page = $request->get('page');
        $status = $request->query('status');
        if($status == NULL ){
            $status = 1;
        }
        if($status == "active"){
            $status = 1;
        }
        if($status == "deactivated"){
            $status = 0;
        }
        if($status != "active" || $status != "deactivated"){
            $status = 1;
        }
        $Supplier = Supplier::withTrashed()
        ->where(function($query) use($status){
            $query->where('is_active', $status);
        })
        ->where(function($query) use($search){
            $query->where('supplier_name', 'LIKE', "%{$search}%" )
            ->where('address', 'LIKE', "%{$search}%" )
            ->where('contact_no', 'LIKE', "%{$search}%" );
        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        return $Supplier;
    }
}
