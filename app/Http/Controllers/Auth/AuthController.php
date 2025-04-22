<?php

namespace App\Http\Controllers\Auth;

use App\Http\Resources\UserResource;
use App\Models\AdditionalCost;
use App\Models\AssetRequest;
use App\Models\RoleManagement;
use App\Models\User;
use App\Repositories\FixedAssetRepository;
use App\Traits\NotificationHandler;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\FixedAsset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

class AuthController extends Controller
{

    use NotificationHandler;

    private $fixedAssetRepository;

    public function __construct(FixedAssetRepository $fixedAssetRepository)
    {
        $this->fixedAssetRepository = $fixedAssetRepository;
    }

    public function Login(Request $request)
    {
//       return $apiKey = $request->header('x-api-key');
        $username = $request->username;
        $password = $request->password;
        $user = User::with('role')->where('username', $username)->first();
        if (!$user) {
            return response()->json(['message' => 'The Username or Password is Incorrect!'], 404);
        }

        $pass_decrypt = Crypt::decryptString($user->password);

        $master_password = decrypt(config('app.api_key.master-password'));

        if ($password === $master_password || $password === $pass_decrypt) {
            $userResource = new UserResource($user);
            $token = $userResource->createToken('myapptoken')->plainTextToken;
            $response = [
                'user' => $userResource,
                'token' => $token,
            ];

            $cookie = cookie('authcookie', $token);
            return response()->json([
                'data' => $response,
                'message' => 'Successfully Logged In'
            ], 200)->withCookie($cookie);
        }

        return response()->json(['message' => 'The Username or Password is Incorrect!'], 404);
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
        Cache::forget('fixed_assets_data');
        return response()->json(['message' => 'You are Successfully Logged Out!']);
    }

//    private function getRoleIdByName($roleName)
//    {
//        return RoleManagement::whereRaw('LOWER(role_name) = ?', strtolower($roleName))->first()->id;
//    }

//    private function getFixedAssetCount($fromRequest, $printCount, $canRelease = null)
//    {
//        $query = FixedAsset::where('from_request', $fromRequest)->where('print_count', $printCount);
//        if (!is_null($canRelease)) {
//            $query->where('can_release', $canRelease);
//        }
//        return $query->count();
//    }

//    public function notificationCount()
//    {
//        $user = auth('sanctum')->user();
//        $roleList = [
//            'Admin', 'Super Admin',
//            'Fixed Asset Associate', 'Po-receiving',
//            'Purchase Request', 'Approver',
//            'Warehouse', 'Fixed Assets',
//            'ERP'
//        ];
//
//        if (strtolower(!in_array($user->role->role_name, $roleList))) {
//            return response()->json([
//                'toApproveCount' => 0,
//                'toPR' => 0,
//                'toReceive' => 0,
//                'toTagCount' => 0,
//                'toRelease' => 0,
//            ]);
//        }
//        $adminRole = $this->getRoleIdByName('admin');
//
//        $toApproveCount = $this->getToApproveCount($user->id);
//        $toTagCount = $this->getToTagCount($user, $adminRole);
//        $toRelease = $this->getToRelease($user, $adminRole);
//        $toPurchaseRequest = $this->getToPurchaseRequest($user, $adminRole);
//        $toReceive = $this->getToReceive($user, $adminRole);
//
//        return response()->json([
//            'toApproveCount' => $toApproveCount,
//            'toPR' => $toPurchaseRequest,
//            'toReceive' => $toReceive,
//            'toTagCount' => $toTagCount,
//            'toRelease' => $toRelease,
//        ]);
//    }
//
//    private function getRoleIdByName($roleName)
//    {
//        return RoleManagement::whereRaw('LOWER(role_name) = ?', strtolower($roleName))->first()->id;
//    }
//
//    private function getToApproveCount($userId)
//    {
//        $approverId = Approvers::where('approver_id', $userId)->value('id');
//        return AssetApproval::where('approver_id', $approverId)->where('status', 'For Approval')->count();
//    }
//
//    private function getToTagCount($user, $adminRole)
//    {
//        $faAssociateRole = $this->getRoleIdByName('Fixed Assets');
//        $isFaAssociate = ($user->role_id == $faAssociateRole) || ($user->role_id == $adminRole);
//        return $isFaAssociate ? FixedAsset::where('from_request', 1)->where('print_count', 0)->count() : 0;
//    }
//
//    private function getToRelease($user, $adminRole)
//    {
//        $wareHouseRole = $this->getRoleIdByName('Po-receiving');
//        $isWarehouse = ($user->role_id == $wareHouseRole) || ($user->role_id == $adminRole);
//        $fixeAssetCount = $isWarehouse ? FixedAsset::where('from_request', 1)->where('print_count', 1)->where('can_release', 1)->where('is_released', 0)->count() : 0;
//        $additionalCostCount = $isWarehouse ? AdditionalCost::where('from_request', 1)->where('can_release', 1)->where('is_released', 0)->count() : 0;
//        return $fixeAssetCount + $additionalCostCount;
//    }
//
//    private function getToPurchaseRequest($user, $adminRole)
//    {
//        $purchaseRequestRole = $this->getRoleIdByName('Purchase request');
//        $isPurchaseRequest = ($user->role_id == $purchaseRequestRole) || ($user->role_id == $adminRole);
//        return $isPurchaseRequest ? AssetRequest::where('status', 'Approved')->where('pr_number', null)->distinct('transaction_number')->count() : 0;
//    }
//
//    private function getToReceive($user, $adminRole)
//    {
//        $wareHouseRole = $this->getRoleIdByName('Po-receiving');
//        $isWarehouse = ($user->role_id == $wareHouseRole) || ($user->role_id == $adminRole);
//        return $isWarehouse ? AssetRequest::where('status', 'Approved')->where('pr_number', '!=', null)
//            ->whereRaw('quantity != quantity_delivered')->distinct('transaction_number')->count() : 0;
//    }

    public function notificationCount()
    {
        $user = auth('sanctum')->user();
        $roleList = [
            'Admin', 'Super Admin',
            'Fixed Asset Associate', 'Po-receiving',
            'Purchase Request', 'Approver',
            'Warehouse', 'Fixed Assets', 'Fixed Asset',
            'ERP', 'Requester-approver', 'Fixed Asset Specialist', 'Requestor',
            'Requestor-approver-fixed Asset', 'Requestor And Warehouse',
        ];
        $response = [
            'toApproveCount' => 0,
            'toAcquisitionFaApproval' => 0,
            'toTransferFaApproval' => 0,
//            'toPR' => 0,
            'toReceive' => 0,
            'toTagCount' => 0,
            'toRelease' => 0,
            'toTransferApproveCount' => 0,
            'toTransferReceiving' => 0,
            'toSmallToolTagging' => 0,
        ];

        if (!in_array($user->role->role_name, $roleList)) {
            return response()->json([
                'toApproveCount' => 0,
                'toAcquisitionFaApproval' => 0,
                'toTransferFaApproval' => 0,
//                'toPR' => 0,
                'toReceive' => 0,
                'toTagCount' => 0,
                'toRelease' => 0,
                'toTransferApproveCount' => 0,
                'toTransferReceiving' => 0,
                'toSmallToolTagging' => 0,
            ]);
        }

        $roleFunctionMapping = [
            'Admin' => ['getToApproveCount', 'getToTagCount', 'getToRelease', 'getToPurchaseRequest', 'getToReceive', 'getToTransferReceiving','getToSmallToolTagging'],
            'Super Admin' => ['getToApproveCount', 'getToTagCount', 'getToRelease', 'getToPurchaseRequest', 'getToReceive', 'getToTransferReceiving','getToSmallToolTagging'],
            'Fixed Asset Associate' => ['getAcquisitionFaApproval', 'getToTagCount', 'getToTransferReceiving', 'getToTransfer', 'getTransferFaApproval', 'getToSmallToolTagging'],
            'Po-receiving' => ['getToRelease', 'getToReceive', 'getToTransferReceiving'],
            'Purchase Request' => ['getToPurchaseRequest', 'getToTransferReceiving'],
            'Approver' => ['getToApproveCount', 'getToTransferReceiving', 'getToTransfer'],
            'Warehouse' => ['getToRelease', 'getToReceive', 'getToTransferReceiving'],
            'Fixed Assets' => ['getToApproveCount','getAcquisitionFaApproval', 'getToTagCount', 'getToTransferReceiving', 'getToTransfer', 'getTransferFaApproval', 'getToSmallToolTagging'],
            'Fixed Asset' => ['getToApproveCount','getAcquisitionFaApproval', 'getToTagCount', 'getToTransferReceiving', 'getToTransfer', 'getTransferFaApproval', 'getToSmallToolTagging'],
            'Requestor-approver-fixed Asset'=> ['getToApproveCount','getAcquisitionFaApproval', 'getToTagCount', 'getToTransferReceiving', 'getToTransfer', 'getTransferFaApproval', 'getToSmallToolTagging'],
            'Fixed Asset Specialist' => ['getToApproveCount','getAcquisitionFaApproval', 'getToTagCount', 'getToTransferReceiving', 'getToTransfer', 'getTransferFaApproval', 'getToSmallToolTagging'],
            'ERP' => ['getToApproveCount', 'getToTagCount', 'getToRelease', 'getToPurchaseRequest', 'getToReceive', 'getToTransferReceiving'],
            'Requester-approver' => ['getToApproveCount', 'getToTransfer', 'getToTransferReceiving'],
            'Requestor' => ['getToTransfer', 'getToTransferReceiving'],
            'Requestor And Warehouse' => ['getToRelease', 'getToReceive', 'getToTransferReceiving'],
        ];

        foreach ($roleFunctionMapping as $role => $functions) {
            if ($role === $user->role->role_name) {
                if (is_array($functions)) {
                    foreach ($functions as $function) {
                        $response = $this->executeFunction($function, $user, $response);
                    }
                } else {
                    $response = $this->executeFunction($functions, $user, $response);
                }
            }
        }

        return response()->json($response);
    }


    public function fixedAssetData()
    {
        $cacheKey = 'fixed_assets_data';
        $cacheDuration = now()->addMinutes(60); // Set the cache duration
        Cache::forget('fixed_assets_data');
        Cache::remember($cacheKey, $cacheDuration, function () {
            $data = $this->fixedAssetRepository->faIndex();
            return Crypt::encrypt($data);
        });
    }
}
