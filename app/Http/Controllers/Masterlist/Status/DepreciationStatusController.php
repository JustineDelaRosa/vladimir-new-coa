<?php

namespace App\Http\Controllers\Masterlist\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\DepreciationStatus\DepreciationStatusRequest;
use App\Models\FixedAsset;
use App\Models\Status\DepreciationStatus;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepreciationStatusController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $depreciationStatus = $request->status ?? 'active';
        $isActiveStatus = ($depreciationStatus === 'deactivated') ? 0 : 1;

        $depreciationStatus = DepreciationStatus::withTrashed()->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPaginate();
        return $depreciationStatus;

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(DepreciationStatusRequest $request)
    {
        $depreciation_status_name = ucwords(strtolower($request->depreciation_status_name));

        $depreciationStatus = DepreciationStatus::create([
            'depreciation_status_name' => $depreciation_status_name
        ]);

        /*return response()->json([
            'message' => 'Successfully created depreciation status.',
            'data' => $depreciationStatus
        ], 200);*/
        return $this->responseCreated('Successfully created depreciation status.');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $depreciationStatus = DepreciationStatus::find($id);
        if (!$depreciationStatus) return $this->responseNotFound('Depreciation status route not found.');

        return response()->json([
            'message' => 'Successfully retrieved depreciation status.',
            'data' => $depreciationStatus
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param DepreciationStatusRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(DepreciationStatusRequest $request, $id)
    {
        $depreciation_status_name = ucwords(strtolower($request->depreciation_status_name));

        $depreciationStatus = DepreciationStatus::find($id);
        if (!$depreciationStatus) return $this->responseNotFound('Depreciation status route not found.');

        //check if no changes
        if ($depreciationStatus->depreciation_status_name == $depreciation_status_name) {
            /*return response()->json([
                'message' => 'No changes were made.'
            ], 200);*/
            return $this->responseUnprocessable('No changes were made.');
        }

        $depreciationStatus->update([
            'depreciation_status_name' => $depreciation_status_name
        ]);

        /*return response()->json([
            'message' => 'Successfully updated depreciation status.',
            'data' => $depreciationStatus
        ], 200);*/

        return $this->responseSuccess('Successfully updated depreciation status.');
    }


    public function archived(DepreciationStatusRequest $request, $id)
    {
        $status = $request->status;

        $depreciationStatus = DepreciationStatus::withTrashed()->find($id);

        if (!$depreciationStatus) {
            /*return response()->json([
                'message' => 'Depreciation Status Route Not Found.'
            ], 404);*/
            return $this->responseNotFound('Depreciation Status Route Not Found.');
        }


        if ($status == $depreciationStatus->is_active) {
            /*return response()->json([
                'message' => 'No Changes.'
            ], 200);*/
            return $this->responseUnprocessable('No Changes.');
        }


        if (!$status) {
            $checkFixedAsset = FixedAsset::where('depreciation_status_id', $id)->exists();
            if ($checkFixedAsset) {
                /*return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'depreciation_status' => [
                            'Depreciation Status is still being used.'
                        ]
                    ]
                ], 422);*/
                return $this->responseUnprocessable('Depreciation Status is still being used.');
            }

            $depreciationStatus->is_active = false;
            $depreciationStatus->save();
            $depreciationStatus->delete();
            /*return response()->json([
                'message' => 'Successfully archived Depreciation Status.',
            ], 200);*/
            return $this->responseSuccess('Successfully archived Depreciation Status.');
        } else {
            $depreciationStatus->restore();
            $depreciationStatus->is_active = true;
            $depreciationStatus->save();
            /*return response()->json([
                'message' => 'Successfully restored Depreciation Status.',
            ], 200);*/

            return $this->responseSuccess('Successfully restored Depreciation Status.');
        }
    }
}
