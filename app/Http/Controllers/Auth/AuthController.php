<?php

namespace App\Http\Controllers\Auth;

use App\Http\Resources\UserResource;
use App\Models\AdditionalCost;
use App\Models\AssetRequest;
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

        //add to user resource
        $user = new UserResource($user);

        $token = $user->createToken('myapptoken')->plainTextToken;
        $response = [
            'user' => $user,
            'token' => $token,
        ];

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

//    private function getRoleIdByName($roleName)
//    {
//        return RoleManagement::whereRaw('LOWER(role_name) = ?', strtolower($roleName))->first()->id;
//    }

    private function getFixedAssetCount($fromRequest, $printCount, $canRelease = null)
    {
        $query = FixedAsset::where('from_request', $fromRequest)->where('print_count', $printCount);
        if (!is_null($canRelease)) {
            $query->where('can_release', $canRelease);
        }
        return $query->count();
    }

    public function notificationCount()
    {
        $user = auth('sanctum')->user();
        $adminRole = $this->getRoleIdByName('admin');

        $toApproveCount = $this->getToApproveCount($user->id);
        $toTagCount = $this->getToTagCount($user, $adminRole);
        $toRelease = $this->getToRelease($user, $adminRole);
        $toPurchaseRequest = $this->getToPurchaseRequest($user, $adminRole);
        $toReceive = $this->getToReceive($user, $adminRole);

        return response()->json([
            'toApproveCount' => $toApproveCount,
            'toPR' => $toPurchaseRequest,
            'toReceive' => $toReceive,
            'toTagCount' => $toTagCount,
            'toRelease' => $toRelease,
        ]);
    }

    private function getRoleIdByName($roleName)
    {
        return RoleManagement::whereRaw('LOWER(role_name) = ?', strtolower($roleName))->first()->id;
    }

    private function getToApproveCount($userId)
    {
        $approverId = Approvers::where('approver_id', $userId)->value('id');
        return AssetApproval::where('approver_id', $approverId)->where('status', 'For Approval')->count();
    }

    private function getToTagCount($user, $adminRole)
    {
        $faAssociateRole = $this->getRoleIdByName('Fixed Asset Associate');
        $isFaAssociate = ($user->role_id == $faAssociateRole) || ($user->role_id == $adminRole);
        return $isFaAssociate ? FixedAsset::where('from_request', 1)->where('print_count', 0)->count() : 0;
    }

    private function getToRelease($user, $adminRole)
    {
        $wareHouseRole = $this->getRoleIdByName('Warehouse');
        $isWarehouse = ($user->role_id == $wareHouseRole) || ($user->role_id == $adminRole);
        $fixeAssetCount = $isWarehouse ? FixedAsset::where('from_request', 1)->where('print_count', 1)->where('can_release', 1)->where('is_released', 0)->count() : 0;
        $additionalCostCount = $isWarehouse ? AdditionalCost::where('from_request', 1)->where('can_release', 1)->where('is_released', 0)->count() : 0;
        return $fixeAssetCount + $additionalCostCount;
    }

    private function getToPurchaseRequest($user, $adminRole)
    {
        $purchaseRequestRole = $this->getRoleIdByName('Purchase Request');
        $isPurchaseRequest = ($user->role_id == $purchaseRequestRole) || ($user->role_id == $adminRole);
        return $isPurchaseRequest ? AssetRequest::where('status', 'Approved')->where('pr_number', null)->distinct('transaction_number')->count() : 0;
    }

    private function getToReceive($user, $adminRole)
    {
        $wareHouseRole = $this->getRoleIdByName('Warehouse');
        $isWarehouse = ($user->role_id == $wareHouseRole) || ($user->role_id == $adminRole);
        return $isWarehouse ? AssetRequest::where('status', 'Approved')->where('pr_number', '!=', null)
            ->whereRaw('quantity != quantity_delivered')->distinct('transaction_number')->count() : 0;
    }
}
