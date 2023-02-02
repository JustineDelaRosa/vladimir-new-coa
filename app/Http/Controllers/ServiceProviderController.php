<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ServiceProvider;
use App\Http\Requests\ServiceProvider\ServiceProviderRequest;
use Illuminate\Validation\Rule;

class ServiceProviderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // return $request->method();
        return $request->url();

        $ServiceProvider = ServiceProvider::get();
        return $ServiceProvider;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ServiceProviderRequest $request)
    {
        $service_provider_name = $request->service_provider_name;
        $ServiceProvider = ServiceProvider::query();
        $create = $ServiceProvider->create([
            "service_provider_name" => $service_provider_name,
            "is_active" => 1
        ]);
        
        return response()->json(['message' => 'Successfully Created!', 'data' => $create]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ServiceProviderRequest $request, $id)
    {
        $getById = ServiceProvider::where('id', $id)->first();
        return $getById;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ServiceProviderRequest $request, $id)
    {
        $service_provider_name = $request->service_provider_name;
        $update = ServiceProvider::where('id', $id)->update(['service_provider_name' => $service_provider_name]);
        return response()->json(['message' => 'Successfully Updated!'], 200);
    
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

    public function archived(ServiceProviderRequest $request, $id){

        $status = $request->status; 
        $ServiceProvider = ServiceProvider::query();
        if(!$ServiceProvider->withTrashed()->where('id', $id)->exists()){
            return response()->json(['message' => 'The given data was invalid.', 'errors' => ['id'=>['Service Provider Not Found']]], 404);
        }

        if($status == false){
            if(!ServiceProvider::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{
                $updateStatus = $ServiceProvider->where('id', $id)->update(['is_active' => false]);
                $ServiceProvider->where('id',$id)->delete();
                return response()->json(['message' => 'Service Provider Successfully Deactived!'], 200);
            }
        }
        if($status == true){
            if(ServiceProvider::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{              
                $restoreUser = $ServiceProvider->withTrashed()->where('id',$id)->restore();
                $updateStatus = $ServiceProvider->update(['is_active' => true]); 
                return response()->json(['message' => 'ServiceProvider Successfully Activated!'], 200);

            }

        }

    }



}
