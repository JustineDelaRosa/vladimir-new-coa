<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use App\Repositories\FixedAssetExportRepository;
use App\Repositories\FixedAssetRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class FixedAssetExportController extends Controller
{
    protected $fixedAssetRepository;

    public function __construct()
    {
        $this->fixedAssetRepository = new FixedAssetExportRepository();
    }

    public function export(Request $request)
    {

        $search = $request->get('search', null);
        $startDate = $request->get('startDate', null);
        $endDate = $request->get('endDate', null);
        return $this->fixedAssetRepository->export($search, $startDate, $endDate);
    }
}
