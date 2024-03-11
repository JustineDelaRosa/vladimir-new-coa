<?php

namespace App\Http\Controllers\Setup;

use App\Models\Module;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SetupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
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
        
    }

    public function createDepartment(Request $request){
        $department_name = $request->department_name;

        if(Department::where('department_name', $department_name)->exists()){
            return response()->json(['message' => 'Department is already Exists!!'], 409);
        }
        $create = Department::create([
            'department_name' => $department_name
        ]);

        return response()->json(['message' => 'Successfully Created', 'data' => $create], 201);
    }


    public function createRole(Request $request){
        $role_name = $request->role_name;
        if(Role::where('role_name', $role_name)->exists()){
            return response()->json(['message' => 'Role is already Exists!!'], 409);
        }
        $create = Role::create([
            'role_name' => $role_name
        ]);
        return response()->json(['message' => 'Successfully Created', 'data' => $create], 201);
        
    }

    public function createModule(Request $request){
        $module_name = $request->module_name;
        if(Module::where('module_name', $module_name)->exists()){
            return response()->json(['message' => 'Module is already Exists!!'], 409);
        }
        $create = Module::create([
            'module_name' => $module_name,
            'is_active' => true
        ]);
        return response()->json(['message' => 'Successfully Created', 'data' => $create], 201);
    }

    public function getModule(Request $request){
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
        $module = Module::withTrashed()
        ->where(function($query) use($status){
            $query->where('is_active', $status);
        })
        ->where(function($query) use($search){
            $query->where('module_name', 'LIKE', "%{$search}%" );
    
        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        return $module;
    }

    public function archived(Request $request, $id){
        $status = $request->status; 
        $Module = Module::query();
        if(!$Module->withTrashed()->where('id', $id)->exists()){
            return response()->json(['message' => 'The given data was invalid.', 'errors' => ['id'=>['Module Not Found']]], 404);
        }
        $validated = $request->validate([
            'status' => 'required|boolean'
        ]);
        if($status == false){
            if(!Module::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{
                $updateStatus = $Module->where('id', $id)->update(['is_active' => false]);
                $Module->where('id',$id)->delete();
                return response()->json(['message' => 'Successfully Deactived!'], 200);
            }
        }
        if($status == true){
            if(Module::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{              
                $restoreModule = $Module->withTrashed()->where('id',$id)->restore();
                $updateStatus = $Module->update(['is_active' => true]); 
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
        }
    }

    public function updateModule(Request $request, $id){
        $id = $request->route('id');
        
        $module_name = $request->module_name;
        $update = Module::where('id', $id)->update(['module_name' => $module_name]);
        return response()->json(['message' => 'Module Successfully Updated!']);
    }

    public function getModuleId(Request $request, $id){
        $module = Module::query();
        $getId = $module->where('id', $id)->first();
        return $getId; 
    }



 

}
