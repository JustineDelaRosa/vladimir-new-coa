<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $creditStatus = $request->status ?? 'active';
        $isActiveStatus = ($creditStatus === 'deactivated') ? 0 : 1;

        $credit = Credit::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $credit;
    }

    public function store(Request $request)
    {
        $creditData = $request->input('result');
        if (empty($request->all()) || empty($request->input('result'))) {
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach ($creditData as $credit) {
            $syncId = $credit['id'];
            $code = $credit['code'];
            $name = $credit['name'];
            $isActive = $credit['deleted_at'];

            Credit::updateOrCreate(
                [
                    'sync_id' => $syncId
                ],
                [
                    'credit_code' => $code,
                    'credit_name' => $name,
                    'is_active' => $isActive == null ? 1 : 0

                ],
            );
        }
        return $this->responseSuccess('Credit successfully saved');
    }
}
