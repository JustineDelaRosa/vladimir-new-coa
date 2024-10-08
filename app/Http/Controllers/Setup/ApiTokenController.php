<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiTokenRequest\CreateApiTokenRequest;
use App\Models\ApiToken;
use App\Models\YmirPRTransaction;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ApiTokenController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {

//        return YmirPRTransaction::get();

        $status = $request->input('status', 'active');
        $isActiveStatus = ($status === 'deactivated') ? 0 : 1;

        $apiTokens = ApiToken::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();

        return $apiTokens;
    }


    public function store(CreateApiTokenRequest $request)
    {
        $token = $request->input('token');
        $p_name = $request->input('p_name');

        $hashedToken = Crypt::encryptString($token);
        $code = Str::uuid();
        ApiToken::create([
            'token' => $hashedToken,
            'code' => $code,
            'p_name' => ucwords(strtolower($p_name))
        ]);

        return $this->responseSuccess('Api Token Created');
    }

    public function getToken($projectName)
    {
        $projectName = ucwords(strtolower($projectName));
        $apiToken = ApiToken::where('p_name', $projectName)->first();
        if ($apiToken) {
            $token = Crypt::decryptString($apiToken->token);
            return $this->responseSuccess('Api Token', ['token' => $token]);
        }
        return $this->responseNotFound('Api Token not found');
    }


    public function update(Request $request, $id)
    {
        $request = $request->input('token');

        $apiToken = ApiToken::find($id);
        if ($apiToken) {
            $hashedToken = Crypt::encryptString($request);
            $apiToken->update([
                'token' => $hashedToken
            ]);
            return $this->responseSuccess('Api Token Updated', $apiToken);
        }
        return $this->responseNotFound('Api Token not found');
    }


    public function archived(Request $request, $id)
    {
        $status = $request->input('status');


        $apiToken = ApiToken::withTrashed()->find($id);
        if (!$apiToken) {
            return $this->responseNotFound('Api Token not found');
        }

        $isActive = ApiToken::withTrashed()->where('id', $id)->where('is_active', true)->exists();

        if (!$status) {

            if (!$isActive) {
                return $this->responseSuccess('No changes made');
            }

            $apiToken->update(['is_active' => $status]);
            $apiToken->delete();
            return $this->responseSuccess('Api Token Archived');
        }

        if ($status) {
            if ($isActive) {
                return $this->responseSuccess('No changes made');
            }

            $apiToken->restore();
            $apiToken->update(['is_active' => $status]);
            return $this->responseSuccess('Api Token Restored');
        }
    }
}
