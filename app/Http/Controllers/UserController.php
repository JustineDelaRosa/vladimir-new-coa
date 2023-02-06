<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\User;
use App\Models\Sedar;
use App\Models\Module;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Models\Access_Permission;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = User::with('modules')->with('department')->get();
        return $user;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(UserRequest $request)
    {
        $employee_id = $request->employee_id;
        $first_name = $request->first_name;
        $middle_name = $request->middle_name;
        $last_name = $request->last_name;
        $department= $request->department;
        $position = $request->position;
        $username = $request->username;
        $password = $request->password;
        $accessPermission = $request->access_permission;
        // $accessPermissionConvertedToString = implode(", ",$access_permission);

        $user = User::query();
        // $department = Department::query();
        $access_permission = Access_Permission::query();
        
        if($user->where('employee_id', $employee_id)->exists()){
            return response()->json(['message'=>'User Already Exists!'], 409);
        }
        // if(!$department->where('id', $department_id)->exists()){
        //     return response()->json(['message'=>'Department Not Exists!'], 404);
        // }
        $createUser = $user->create([
            'employee_id' => $employee_id,
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'last_name' => $last_name,
            'department' => $department,
            'position' => $position,
            'username' => $username,
            'password' => Crypt::encryptString($username,),
            'is_active' => 1
        ]);
        $userid = $createUser->id;
        $moduleNotExist =[];
        $moduleExist =[];
        foreach($accessPermission as $permission_id){
          //  $modules = Module::query();
            if(!Module::where('id', $permission_id)->exists()){
                array_push($moduleNotExist, $permission_id);
            }
            else{
                $access_permission_create = $access_permission->create([
                    'module_id' => $permission_id,
                    'user_id' => $userid
                ]);  
                 $module_id = $access_permission_create->module_id;
                 $module = Module::where('id', $module_id)->first();
                array_push($moduleExist, $module);   
            }
        }
        
        return response()->json([
            'message' => 'Successfully',
            'data' => $createUser,
            'module' => $moduleExist
        ], 201);

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
       $username = $request->username;
       $accessPermission = $request->access_permission;

       $user = User::where('id', $id);
       $check_user =  User::where('username', $username)->exists();

       if(!$check_user){
        $user_update = $user->update([
         "username" => $username
        ]);
        // $user_changed = $user_update->username->first();
        $user_changed = $username;
        

    }
    else{
     $user_changed = 'Nothing has Changed'; 
    }



       $access_permission = Access_Permission::query();
    //    $user_update = $user->update([
    //     "username" => $username
    //    ]);
    
        $moduleNotExist =[];
        $moduleUpdated =[];
        $not_included = $access_permission->where('user_id', $id)->get();
        foreach($not_included as $notIncluded){
         $module_id_not_exist_in_array =  "$notIncluded->module_id";
         if(!in_array($module_id_not_exist_in_array, $accessPermission)){
            Access_Permission::where('user_id', $id)->where('module_id', $module_id_not_exist_in_array)->delete();
         }

        }
        foreach($accessPermission as $permission_id){
            if(!Module::where('id', $permission_id)->exists()){
                array_push($moduleNotExist, $permission_id);
            }
            else{
                if(!(Access_Permission::where('module_id', $permission_id)->where('user_id', $id)->exists())){
                    $access_permission_create = $access_permission->create([
                        'module_id' => $permission_id,
                        'user_id' => $id
                    ]); 
                    array_push($moduleUpdated, $access_permission_create);
                }
            }
        }

        if(empty($moduleUpdated)){
            $moduleUpdated ='Nothing has Changed!';
        }


        // 'message' => 'Successfully Updated!',
        //     'username' => $user->first()->username,
        //     'module_added' => $moduleUpdated
        return response()->json([
           'userdata' => [
            'message' => 'Successfully Updated!',
            // 'username' => $user->first()->username,
            'username' => $user_changed, 
            'module_added' => $moduleUpdated
           ] 
        ], 201);

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
        $user = User::withTrashed()->with('modules')
        ->where(function($query) use($status){
            $query->where('is_active', $status);
        })
        ->where(function($query) use($search){
            $query->where('employee_id', 'LIKE', "%{$search}%" )
            ->orWhere('first_name', 'LIKE', "%{$search}%" )
            ->orWhere('middle_name', 'LIKE', "%{$search}%" )
            ->orWhere('last_name', 'LIKE', "%{$search}%" )
            ->orWhere('position', 'LIKE', "%{$search}%" )
            ->orWhere('username', 'LIKE', "%{$search}%" );

        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        return $user;
    } 










    public function archived(Request $request, $id){
       
        $status = $request->status; 
        $User = User::query();
        if(!$User->withTrashed()->where('id', $id)->exists()){
            return response()->json(['message' => 'The given data was invalid.', 'errors' => ['id'=>['User Not Found']]], 404);
        }
        $validated = $request->validate([
            'status' => 'required|boolean'
        ]);
        if($status == false){
            if(!User::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{
                $updateStatus = $User->where('id', $id)->update(['is_active' => false]);
                $User->where('id',$id)->delete();
                return response()->json(['message' => 'User Successfully Deactived!'], 200);
            }
        }
        if($status == true){
            if(User::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{              
                $restoreUser = $User->withTrashed()->where('id',$id)->restore();
                $updateStatus = $User->update(['is_active' => true]); 
                return response()->json(['message' => 'User Successfully Activated!'], 200);

            }

        }

    }

    public function test(Request $request){

        try{
            $find  = User::find(2);
        }
        catch(Exception $e){
            return "not exist";
        }
    }





    
}

