<?php

namespace App\Http\Controllers\API\PrRecon;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\YmirPRTransaction;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PrReconController extends Controller
{
    public function prReconViewing(Request $request)
    {
        $yearMonth = $request->input('year_month'); // Format: YYYY-MM

// Filter YmirPRTransaction by year_month
        $ymirPRData = YmirPRTransaction::whereRaw("INSTR(pr_year_number_id, 'FA') > 0")
            ->when($yearMonth, function ($query) use ($yearMonth) {
                $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$yearMonth]);
            })
            ->get(['pr_number', 'pr_year_number_id', 'created_at']);

        $ymirPRNumbers = $ymirPRData->pluck('pr_number')->toArray();

// Filter AssetRequest to only those in YmirPRNumbers
        $vladPRData = AssetRequest::withTrashed()
            ->whereNotNull('pr_number')
            ->whereIn('pr_number', $ymirPRNumbers)
            ->get(['pr_number', 'transaction_number', 'created_at']);

        $vladPRs = $vladPRData->pluck('pr_number')->toArray();

// Create a combined unique set of all PR numbers
        $allPRs = array_values(array_unique(array_merge($ymirPRNumbers, $vladPRs)));

// Generate the comparison results
        $results = [];
        foreach ($allPRs as $pr) {
            $ymirItem = $ymirPRData->firstWhere('pr_number', $pr);
            $ymirPRYearId = $ymirItem ? $ymirItem->pr_year_number_id : null;

            $inYmir = in_array($pr, $ymirPRNumbers);
            $inVlad = in_array($pr, $vladPRs);

            $results[] = [
                'ymir_pr' => $inYmir ? $ymirPRYearId : '-',
                'vlad_pr' => $inVlad ? $ymirPRYearId : '-',
                'status' => $inYmir && $inVlad ? 'Match' : ($inYmir ? 'vlad pr missing' : 'ymir pr missing'),
                'date' => $ymirItem ? $ymirItem->created_at : null,
            ];
        }

        $perPage = (int) $request->input('per_page', 0);
        $page = (int) $request->input('page', 1);


        if ($perPage > 0) {
            $collection = collect($results);
            $total = $collection->count();
            $items = $collection->forPage($page, $perPage)->values();
            $paginator = new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            return response()->json($paginator);
        }

        return $results;
    }
}
