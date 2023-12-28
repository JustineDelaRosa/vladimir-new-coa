<?php

namespace App\Traits;

use App\Models\AssetRequest;

trait AddPRHandler
{
    public function activityLog($assetRequest, $prNumber)
    {


        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        activity()
            ->causedBy($user)
            ->performedOn($assetRequests)
            ->withProperties($this->composeLogProperties($assetRequest, $prNumber))
            ->inLog($prNumber === null ? 'Removed PR Number' : 'Added PR Number')
            ->tap(function ($activity) use ($user, $assetRequest, $prNumber) {
                $firstAssetRequest = $assetRequest->first();
                if ($firstAssetRequest) {
                    $activity->subject_id = $firstAssetRequest->transaction_number;
                }
            })
            ->log($prNumber === null ? 'PR Number was removed by ' . $user->employee_id . '.' :
                'PR Number ' . $prNumber . ' has been added by ' . $user->employee_id . '.');
    }

    private function composeLogProperties($assetRequest, $prNumber = null): array
    {
        $requestor = $assetRequest->first()->requestor;
        return [
            'requestor' => [
                'id' => $requestor->id,
                'firstname' => $requestor->firstname,
                'lastname' => $requestor->lastname,
                'employee_id' => $requestor->employee_id,
            ],
            'pr_number' => $prNumber ?? null,
            'remarks' => $assetRequest->first()->remarks ?? null,
        ];
    }
}
