<?php

namespace App\Http\Controllers\Setup;

use App\Models\User;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use App\Models\RoleManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleManagement\RoleManagementRequest;

class RoleManagementController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $roleManagementStatus = $request->status ?? 'active';
        $isActiveStatus = ($roleManagementStatus === 'deactivated') ? 0 : 1;

        $roleManagement = RoleManagement::withTrashed()->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPaginate();

        $roleManagement->transform(function ($roleManagement) {
            $roleManagement->access_permission = explode(", ", $roleManagement->access_permission);
            return $roleManagement;
        });
        return $roleManagement;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(RoleManagementRequest $request)
    {
        $role_name = ucwords(strtolower($request->role_name));
        $access_permission = $request->access_permission;
        $accessConvertedToString = implode(", ",$access_permission);
        $create = RoleManagement::create([
            'role_name' => $role_name,
            'access_permission' => $accessConvertedToString,
            'is_active' => 1
        ]);
//        return response()->json(['message' => 'Successfully Created', 'data'=>$create]);
        return $this->responseCreated('Successfully Created');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $RoleManagement = RoleManagement::find($id);
        if(!$RoleManagement){
//            return response()->json(['error' => 'Role Management Route Not Found'], 404);
            return $this->responseNotFound('Role Management Route Not Found');
        }
        return $RoleManagement->where('id', $id)->first();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(RoleManagementRequest $request, $id)
    {
        $role_name = ucwords(strtolower($request->role_name));
        $access_permission = $request->access_permission;
        $accessConvertedToString = implode(", ",$access_permission);
        $RoleManagement = RoleManagement::find($id);
        if(!$RoleManagement){
//            return response()->json(['error' => 'Role Management Route Not Found'], 404);
            return $this->responseNotFound('Role Management Route Not Found');
        }
        if($RoleManagement->where('role_name', $role_name)->exists()){
            $get_permission = $RoleManagement->access_permission;
            $permission_to_array = explode(", ", $get_permission);
            $compare_permission = (array_diff($permission_to_array,$access_permission) || array_diff($access_permission,$permission_to_array));
            if(empty($compare_permission)){
//                return response()->json(['message' => 'No Changes'], 200);
                return $this->responseSuccess('No Changes');
            }
        }
        $update = RoleManagement::where('id',$id)
         ->update([
            'role_name' => $role_name,
            'access_permission' => json_encode($accessConvertedToString),
            'is_active' => 1
        ]);
//        return response()->json(['message' => 'Successfully Updated!'], 200);
        return $this->responseSuccess('Successfully Updated!');
    }

    /**
     * Remove the specified resource from storage.

     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function archived(RoleManagementRequest $request, $id){
        $status = $request->status;
        $RoleManagement = RoleManagement::query();
        if(!$RoleManagement->withTrashed()->where('id',$id)->exists()){
//            return response()->json(['error' => 'Role Management Route Not Found'], 404);
            return $this->responseNotFound('Role Management Route Not Found');
        }
        if(User::where('role_id', $id)->exists()){
            if($status == true){
//                return response()->json(['message' => 'No Changes'],200);
                return $this->responseSuccess('No Changes');
            }
            else{
//                return response()->json(['message' => 'Unable to Archive, Role already in used!'],409);
                return $this->responseConflictError('Unable to Archive, Role already in used!');
            }
        }

        if($status == false){
            if(!RoleManagement::where('id', $id)->where('is_active', true)->exists()){
//                return response()->json(['message' => 'No Changes'], 200);
                return $this->responseSuccess('No Changes');
            }
            else{
                $updateStatus = $RoleManagement->where('id', $id)->update(['is_active' => false]);
                $RoleManagement->where('id',$id)->delete();
//                return response()->json(['message' => 'Successfully Deactived!'], 200);
                return $this->responseSuccess('Successfully Deactived!');
            }
        }
        if($status == true){
            if(RoleManagement::where('id', $id)->where('is_active', true)->exists()){
//                return response()->json(['message' => 'No Changes'], 200);
                return $this->responseSuccess('No Changes');
            }
            else{
                $restoreUser = $RoleManagement->withTrashed()->where('id',$id)->restore();
                $updateStatus = $RoleManagement->update(['is_active' => true]);
//                return response()->json(['message' => 'Successfully Activated!'], 200);
                return $this->responseSuccess('Successfully Activated!');
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
        $RoleManagement = RoleManagement::withTrashed()
        ->where(function($query) use($status){
            $query->where('is_active', $status);
        })
        ->where(function($query) use($search){
            $query->where('role_name', 'LIKE', "%{$search}%" );
        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        foreach($RoleManagement as $access){
            $access_permission = explode(", ", $access->access_permission);

            $access['access_permission'] = $access_permission;
        }
        return $RoleManagement;
    }
}
