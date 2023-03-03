<?php

namespace App\Http\Controllers;

use App\Models\CategoryList;
use Illuminate\Http\Request;
use App\Models\ServiceProvider;
use Illuminate\Validation\Rule;
use App\Http\Requests\ServiceProvider\ServiceProviderRequest;

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
        // return $request->url();

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
       $ServiceProvider = ServiceProvider::query();
       if(!$ServiceProvider->where('id', $id)->exists()){
        return response()->json(['error' => 'Service ProviderRoute Not Found'], 404);
       }
       return $ServiceProvider->where('id', $id)->first();
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
        if(!ServiceProvider::where('id', $id)->exists()){
            return response()->json(['error' => 'Service Provider Route Not Found'], 404);
        }
        if(ServiceProvider::where('id',$id)->where('service_provider_name', $service_provider_name)->exists()){
            return response()->json(['message' => 'No Changes'], 200);
        }
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
            return response()->json(['error' => 'Service Provider Route Not Found'], 404);
        }

        if($status == false){
            if(!ServiceProvider::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{
                if(!CategoryList::where('service_provider_id', $id)->exists()){
                    $updateStatus = $ServiceProvider->where('id', $id)->update(['is_active' => false]);
                    $ServiceProvider->where('id',$id)->delete();
                    return response()->json(['message' => 'Successfully Deactived!'], 200);
                }
                return response()->json(['message' => 'Unable to Archived!, Service Provider was tagged!']);
            }
        }
        if($status == true){
            if(ServiceProvider::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{              
                $restoreUser = $ServiceProvider->withTrashed()->where('id',$id)->restore();
                $updateStatus = $ServiceProvider->update(['is_active' => true]); 
                return response()->json(['message' => 'Successfully Activated!'], 200);
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
        $ServiceProvider = ServiceProvider::withTrashed()
        ->where(function($query) use($status){
            $query->where('is_active', $status);
        })
        ->where(function($query) use($search){
            $query->where('service_provider_name', 'LIKE', "%{$search}%" );
        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        return $ServiceProvider;
    } 



}
