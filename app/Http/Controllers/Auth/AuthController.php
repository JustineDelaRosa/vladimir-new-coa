<?php

namespace App\Http\Controllers\Auth;

use App\Models\RoleManagement;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\FixedAsset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

class AuthController extends Controller
{
    public function Login(Request $request)
    {
        $username = $request->username;
        $password = $request->password;
        $user = User::with('role')->where('username', $username)->first();
        if (!$user) {
            return response()->json(['message' => 'The Username or Password is Incorrect!'], 404);
        }
        $pass_decrypt = Crypt::decryptString($user->password);
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $toApproveCount = AssetApproval::where('approver_id', $approverId)->where('status', 'For Approval')->count();

        $faAssociateRole = $this->getRoleIdByName('Fixed Asset Associate');
        $isFaAssociate = $user->role_id == $faAssociateRole;
        $toTagCount = $isFaAssociate ? $this->getFixedAssetCount(1, 0) : 0;

        $wareHouseRole = $this->getRoleIdByName('Warehouse');
        $isWarehouse = $user->role_id == $wareHouseRole;
        $toRelease = $isWarehouse ? $this->getFixedAssetCount(1, 1, 1) : 0;

        //if Username and password match
        // if ($username == $pass_decrypt) {
        //     return response()->json(['message' => 'Successfully Logged In!', 'data' => [
        //         'username' => $username,
        //         'password' => $pass_decrypt
        //     ]], 200);
        // }


        if ((!$user) || $password != $pass_decrypt) {
            return response([
                'message' => 'The Username or Password is Incorrect!'
            ], 401);
        }

        $token = $user->createToken('myapptoken')->plainTextToken;
        $response = [
            'user' => $user,
            'token' => $token,
            'sessionTime' => config('sanctum.expiration'),
            'toApproveCount' => $toApproveCount ?? 0,
            'toTagCount' => $toTagCount ?? 0,
            'toRelease' => $toRelease ?? 0,
        ];
        //        $cookie = cookie('authcookie', $token);
        //        return response()->json([
        //            'data' => [
        //                'user' => [
        //                    'id' => $user->id,
        //                    'employee_id' => $user->employee_id,
        //                    'firstname' => $user->firstname,
        //                    'lastname' => $user->lastname,
        //                    'username' => $user->username,
        //                    'role_id' => $user->role_id,
        //                    'is_active' => $user->is_active,
        //                    'created_at' => $user->created_at,
        //                    'updated_at' => $user->updated_at,
        //                    'deleted_at' => $user->deleted_at,
        //                    'role' => [
        //                        'id' => $user->role->id,
        //                        'role_name' => $user->role->role_name,
        //                        'access_permission' => $user->role->access_permission . ', requester, approver',
        //                        'is_active' => $user->role->is_active,
        //                        'created_at' => $user->role->created_at,
        //                        'updated_at' => $user->role->updated_at,
        //                        'deleted_at' => $user->role->deleted_at,
        //                    ],
        //                ],
        //                'token' => $token
        //            ],
        //            'message' => 'Successfully Logged In'
        //        ], 200)->withCookie($cookie);



        $cookie = cookie('authcookie', $token);
        return response()->json([
            'data' => $response,
            'message' => 'Successfully Logged In'
        ], 200)->withCookie($cookie);
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::where('id', $id)->first();
        $username = $user->username;
        $pass_encrypt = Crypt::encryptString($username);
        $user->update([
            'password' => $pass_encrypt
        ]);
        return response()->json(['message' => 'Password has been Reset!']);
    }

    public function changedPassword(Request $request)
    {
        $old_password = $request->old_password;
        $new_password = $request->new_password;
        $auth_id = auth('sanctum')->user()->id;
        $user = User::where('id', $auth_id)->first();

        if ($old_password == $new_password) {
            return response()->json(['message' => 'Old and New Password Match'], 422);
        }
        $decryptedPassword = Crypt::decryptString($user->password);
        if ($old_password != $decryptedPassword) {
            return response()->json(['message' => 'Password not match!!'], 422);
        }
        $encrypted_new_password = Crypt::encryptString($new_password);
        $user->update([
            'password' => $encrypted_new_password
        ]);
        return response()->json(['message' => 'Password Successfully Changed!!']);
    }

    public function Logout(Request $request)
    {
        auth('sanctum')->user()->currentAccessToken()->delete(); //logout currentAccessToken
        return response()->json(['message' => 'You are Successfully Logged Out!']);
    }

    private function getRoleIdByName($roleName) {
        return RoleManagement::whereRaw('LOWER(role_name) = ?', strtolower($roleName))->first()->id;
    }

    private function getFixedAssetCount($fromRequest, $printCount, $canRelease = null) {
        $query = FixedAsset::where('from_request', $fromRequest)->where('print_count', $printCount);
        if (!is_null($canRelease)) {
            $query->where('can_release', $canRelease);
        }
        return $query->count();
    }
}
