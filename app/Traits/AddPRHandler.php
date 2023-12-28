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
            // ->withProperties($this->composeLogProperties($assetRequest, $prNumber))
            ->inLog('Added PR')
            ->tap(function ($activity) use ($user, $assetRequest, $prNumber) {
                $firstAssetRequest = $assetRequest->first();
                if ($firstAssetRequest) {
                    $activity->subject_id = $firstAssetRequest->transaction_number;
                }
            })
            ->log('PR Number ' . $prNumber . ' has been added by ' . $user->employee_id . '.');
    }

    private function composeLogProperties($assetRequest, $prNumber): array
    {
        $requester = $assetRequest->user;

        return [
            'requester' => [
                'id' => $requester->id,
                'firstname' => $requester->firstname,
                'lastname' => $requester->lastname,
                'employee_id' => $requester->employee_id,
            ],
            'pr_number' => $prNumber,
            'remarks' => $assetRequest->remarks ?? null,
        ];
    }
}
