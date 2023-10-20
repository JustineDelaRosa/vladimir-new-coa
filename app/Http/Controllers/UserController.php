<?php

namespace App\Http\Controllers;

use App\Models\Approvers;
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
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Http\Response
     */
    public function index(Request $request)
    {
//        $user = User::with('role')->get();
//        return $user;

        $userStatus = $request->status;
        $isActiveStatus = ($userStatus === "deactivated") ? 0 : 1;

        $user = User::withTrashed()->where('is_active', $isActiveStatus)->useFilters()->dynamicPaginate();

        $user->transform(function ($item) {
            return[
                'id' => $item->id,
                'employee_id' => $item->employee_id,
                'firstname' => $item->firstname,
                'lastname' => $item->lastname,
                'username' => $item->username,
                'role' => $item->role,
                'department' => $item->department->department_name ?? null,
                'subunit' => $item->subunit->sub_unit_name ?? null,
                'is_active' => $item->is_active,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'deleted_at' => $item->deleted_at,
            ];
        });

        return $user;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UserRequest $request)
    {
        $employee_id = $request->employee_id;
        $firstname = ucwords(strtolower($request->firstname));
        $lastname = ucwords(strtolower($request->lastname));
        $username = $request->username;
        $role_id = $request->role_id;
        $department_id = $request->department_id;
        $subunit_id = $request->subunit_id;

        // $accessPermissionConvertedToString = implode(", ",$access_permission);

        $user = User::query();
        $createUser = $user->create([
            'employee_id' => $employee_id,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'username' => $username,
            'password' => Crypt::encryptString($username),
            'department_id' => $department_id,
            'subunit_id' => $subunit_id,
            'is_active' => 1,
            'role_id' => $role_id,
        ]);

        return response()->json(['message' => 'Successfully Created!', 'data' => $createUser], 201);

        // $userid = $createUser->id;
        // $moduleNotExist =[];
        // $moduleExist =[];
        // foreach($accessPermission as $permission_id){
        //   //  $modules = Module::query();
        //     if(!Module::where('id', $permission_id)->exists()){
        //         array_push($moduleNotExist, $permission_id);
        //     }
        //     else{
        //         $access_permission_create = $access_permission->create([
        //             'module_id' => $permission_id,
        //             'user_id' => $userid
        //         ]);
        //          $module_id = $access_permission_create->module_id;
        //          $module = Module::where('id', $module_id)->first();
        //         array_push($moduleExist, $module);
        //     }
        // }

        // return response()->json([
        //     'message' => 'Successfully',
        //     'data' => $createUser,
        //     'module' => $moduleExist
        // ], 201);

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|\Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $User = User::find($id);
        if (!$User) {
            return response()->json(['error' => 'User Route Not Found'], 404);
        }
        $data = User::with('role')->findOrFail($id);
        return $data;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UserRequest $request, $id)
    {
        $employee_id = $request->employee_id;
        $firstname = ucwords(strtolower($request->firstname));
        $lastname = ucwords(strtolower($request->lastname));
        $username = $request->username;
        $role_id = $request->role_id;
        $department_id = $request->department_id;
        $subunit_id = $request->subunit_id;
        $User = User::find($id);
        if (!$User) {
            return response()->json(['error' => 'User Route Not Found'], 404);
        }
        if (User::where('id', $id)->where('username', $username)->where('role_id', $role_id)->exists()) {
            return response()->json(['message' => 'No Changes'], 200);
        }

        $update = User::where('id', $id)
            ->update([
                'username' => $username,
                'role_id' => $role_id,
                'department_id' => $department_id,
                'subunit_id' =>$subunit_id,
            ]);

        return response()->json(['message' => 'Successfully Updated!'], 201);

        //    $username = $request->username;
        //    $accessPermission = $request->access_permission;
        //    $user = User::where('id', $id);
        //    $check_user =  User::where('username', $username)->exists();
        //    if(!$check_user){
        //     $user_update = $user->update([
        //      "username" => $username
        //     ]);
        //     // $user_changed = $user_update->username->first();
        //     $user_changed = $username;
        // }
        // else{
        //  $user_changed = 'Nothing has Changed';
        // }
        //    $access_permission = Access_Permission::query();
        // //    $user_update = $user->update([
        // //     "username" => $username
        // //    ]);
        //     $moduleNotExist =[];
        //     $moduleUpdated =[];
        //     $not_included = $access_permission->where('user_id', $id)->get();
        //     foreach($not_included as $notIncluded){
        //      $module_id_not_exist_in_array =  "$notIncluded->module_id";
        //      if(!in_array($module_id_not_exist_in_array, $accessPermission)){
        //         Access_Permission::where('user_id', $id)->where('module_id', $module_id_not_exist_in_array)->delete();
        //      }
        //     }
        //     foreach($accessPermission as $permission_id){
        //         if(!Module::where('id', $permission_id)->exists()){
        //             array_push($moduleNotExist, $permission_id);
        //         }
        //         else{
        //             if(!(Access_Permission::where('module_id', $permission_id)->where('user_id', $id)->exists())){
        //                 $access_permission_create = $access_permission->create([
        //                     'module_id' => $permission_id,
        //                     'user_id' => $id
        //                 ]);
        //                 array_push($moduleUpdated, $access_permission_create);
        //             }
        //         }
        //     }
        //     if(empty($moduleUpdated)){
        //         $moduleUpdated ='Nothing has Changed!';
        //     }
        //     // 'message' => 'Successfully Updated!',
        //     //     'username' => $user->first()->username,
        //     //     'module_added' => $moduleUpdated
        //     return response()->json([
        //        'userdata' => [
        //         'message' => 'Successfully Updated!',
        //         // 'username' => $user->first()->username,
        //         'username' => $user_changed,
        //         'module_added' => $moduleUpdated
        //        ]
        //     ], 201);

    }

    public function search(Request $request)
    {

        $search = $request->query('search');
        $limit = $request->query('limit');
        $page = $request->get('page');
        $status = $request->query('status');
        if ($status == NULL) {
            $status = 1;
        }
        if ($status == "active") {
            $status = 1;
        }
        if ($status == "deactivated") {
            $status = 0;
        }
        if ($status != "active" || $status != "deactivated") {
            $status = 1;
        }
        $User = User::with('role')->withTrashed()
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('employee_id', 'LIKE', "%{$search}%")
                    ->orWhere('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%")
                    ->orWhereHas('role', function ($q) use ($search) {
                        $q->where('role_name', 'like', '%' . $search . '%');
                    });
            })
            ->orderby('created_at', 'DESC')
            ->paginate($limit);
        return $User;
    }

    public function archived(UserRequest $request, $id)
    {
        $auth_id = auth('sanctum')->user()->id;
        if ($id == $auth_id) {
            return response()->json(['error' => 'Unable to Archive, User already in used!'], 422);
        }
        $status = $request->status;
        $User = User::query();
        if (!$User->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'User Route Not Found'], 404);
        }
        if ($status == false) {
            if (!User::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
//            $userSettingApproverCheck = UserApprover::where('requester_id', $id)->orWhere('approver_id', $id)->exists();
//            if ($userSettingApproverCheck) {
//                return response()->json(['message' => 'User Account still in use'], 409);
//            }
                $ApproverCheck = Approvers::Where('approver_id', $id)->exists();
                if ($ApproverCheck) {
                    return response()->json(['error' => 'User Account still in use'], 422);
                }

                $updateStatus = $User->where('id', $id)->update(['is_active' => false]);
                $User->where('id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactived!'], 200);
            }
        }
        if ($status == true) {
            if (User::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $restoreUser = $User->withTrashed()->where('id', $id)->restore();
                $updateStatus = $User->update(['is_active' => true]);
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
        }

    }
}

