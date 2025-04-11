<?php

namespace App\Http\Controllers\API\PrRecon;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\YmirPRTransaction;
use Illuminate\Http\Request;

class PrReconController extends Controller
{
    public function prReconViewing()
    {

        // Get PR data from both systems
        $ymirPRData = YmirPRTransaction::whereRaw("INSTR(pr_year_number_id, 'FA') > 0")
            ->get(['pr_number', 'pr_year_number_id']);

        // Convert to array of PR numbers for comparison
        $ymirPRs = $ymirPRData->pluck('pr_year_number_id')->toArray();
        $ymirPRNumber = $ymirPRData->pluck('pr_number')->toArray();

        // Get PR numbers from AssetRequest
        $vladPRData = AssetRequest::withTrashed()
            ->whereNotNull('pr_number')
            ->get(['pr_number', 'transaction_number']);
        $vladPRs = $vladPRData->pluck('pr_number')->toArray();
        $transactionNumbers = $vladPRData->pluck('transaction_number')->toArray();


        // Create a combined unique set of all PR numbers
        $allPRs = array_values(array_unique(array_merge($ymirPRNumber, $vladPRs)));

        // Generate the comparison results
        $results = [];
        foreach ($allPRs as $pr) {
            // Find the corresponding pr_year_number_id for this PR number in the Ymir data
            $ymirItem = $ymirPRData->firstWhere('pr_number', $pr);
            $ymirPRYearId = $ymirItem ? $ymirItem->pr_year_number_id : null;

            $inYmir = in_array($pr, $ymirPRNumber);
            $inVlad = in_array($pr, $vladPRs);

            $results[] = [
                'ymir_pr' => $inYmir ? $ymirPRYearId : '-',
                'vlad_pr' => $inVlad ? $ymirPRYearId : '-',
                'status' => $inYmir && $inVlad ? 'Match' : ($inYmir ? 'vlad pr missing' : 'ymir pr missing'),
            ];
        }

        return $results;
    }
}
