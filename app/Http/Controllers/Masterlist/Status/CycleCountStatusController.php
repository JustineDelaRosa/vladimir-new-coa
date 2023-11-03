<?php

namespace App\Http\Controllers\Masterlist\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\CycleCountStatus\CycleCountStatusRequest;
use App\Models\FixedAsset;
use App\Models\Status\CycleCountStatus;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class CycleCountStatusController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return CycleCountStatus|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|\Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $cycleCountStatus = $request->status ?? 'active';
        $isActiveStatus = ($cycleCountStatus === 'deactivated') ? 0 : 1;

        $cycleCountStatus = CycleCountStatus::withTrashed()->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
             ->dynamicPaginate();

        return $cycleCountStatus;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CycleCountStatusRequest $request)
    {
        $cycle_count_status_name = ucwords(strtolower($request->cycle_count_status_name));

        $cycleCountStatus = CycleCountStatus::create([
            'cycle_count_status_name' => $cycle_count_status_name
        ]);

//        return response()->json([
//            'message' => 'Successfully created cycle count status.',
//            'data' => $cycleCountStatus
//        ], 200);

        return $this->responseCreated('Successfully created cycle count status.');

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $cycleCountStatus = CycleCountStatus::find($id);
        if (!$cycleCountStatus) return $this->responseNotFound('Cycle count status route not found.');

        return response()->json([
            'message' => 'Successfully retrieved cycle count status.',
            'data' => $cycleCountStatus
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CycleCountStatusRequest $request, $id)
    {
        $cycle_count_status_name = ucwords(strtolower($request->cycle_count_status_name));

        $cycleCountStatus = CycleCountStatus::find($id);
        if (!$cycleCountStatus) return $this->responseNotFound('Cycle count status route not found.');

        //check if no changes were made
        if ($cycleCountStatus->cycle_count_status_name == $cycle_count_status_name) {
//            return response()->json([
//                'message' => 'No changes were made.'
//            ], 200);
            return $this->responseSuccess('No changes changes.');
        }

        $cycleCountStatus->update([
            'cycle_count_status_name' => $cycle_count_status_name
        ]);

        /*return response()->json([
            'message' => 'Successfully updated cycle count status.',
            'data' => $cycleCountStatus
        ], 200);*/
        return $this->responseSuccess('Successfully updated cycle count status.');
    }

//    public function archived(CycleCountStatusRequest $request, $id)
//    {
//
//        $status = $request->status;
//
//        $cycleCount = CycleCountStatus::query();
//        if(!$cycleCount->withTrashed()->where('id', $id)->exists()){
//            return response()->json([
//                'message' => 'Asset Status Route Not Found.'
//            ], 404);
//        }
//
//        if($status == false){
//            if(!CycleCountStatus::where('id', $id)->where('is_active', true)->exists()){
//                return response()->json([
//                    'message' => 'No Changes.'
//                ], 200);
//            }else{
////                $checkFixedAsset = FixedAsset::where('cycle_count_status_id', $id)->exists();
////                if ($checkFixedAsset) {
////                    return response()->json(['error' => 'Unable to archive, Cycle Count Status is still in use!'], 422);
////                }
//                if(CycleCountStatus::where('id', $id)->exists()){
//                    $updateCapex = CycleCountStatus::Where('id', $id)->update([
//                        'is_active' => false,
//                    ]);
//                    $archiveCapex = CycleCountStatus::where('id', $id)->delete();
//                    return response()->json([
//                        'message' => 'Successfully archived Cycle Count Status.',
//                    ], 200);
//                }
//
//            }
//        }
//
//        if($status == true){
//            if(CycleCountStatus::where('id', $id)->where('is_active', true)->exists()){
//                return response()->json([
//                    'message' => 'No Changes.'
//                ], 200);
//            }else{
//                $restoreCapex = CycleCountStatus::withTrashed()->where('id', $id)->restore();
//                $updateStatus = CycleCountStatus::where('id', $id)->update([
//                    'is_active' => true,
//                ]);
//                return response()->json([
//                    'message' => 'Successfully restored Cycle Count Status.',
//                ], 200);
//            }
//        }
//    }


    public function archived(CycleCountStatusRequest $request, $id)
    {
        $status = $request->status;

        // First check if it exists withTrashed.
        $cycleCount = CycleCountStatus::withTrashed()->find($id);

        if (!$cycleCount) {
            /*return response()->json([
                'message' => 'Asset Status Route Not Found.'
            ], 404);*/
            return $this->responseNotFound('Cycle count status route not found.');
        }

        // If status requested is the same as the current status, no changes are needed.
        if ($status == $cycleCount->is_active) {
            /*return response()->json([
                'message' => 'No Changes.'
            ], 200);*/
            return $this->responseSuccess('No changes.');
        }

        // Perform changes based on requested status.
        if (!$status) {
            $checkFixedAsset = FixedAsset::where('cycle_count_status_id', $id)->exists();
                if ($checkFixedAsset) {
                    /*return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'cycle_count_status' => [
                                'Cycle Count Status is still in use!'
                            ]
                        ]
                    ], 422);*/
                    return $this->responseUnprocessable('Cycle Count Status is still in use!');
                }
            $cycleCount->is_active = false;
            $cycleCount->save();
            $cycleCount->delete();
            /*return response()->json([
                'message' => 'Successfully archived Cycle Count Status.',
            ], 200);*/
            return $this->responseSuccess('Successfully archived Cycle Count Status.');

        } else {
            $cycleCount->restore();
            $cycleCount->is_active = true;
            $cycleCount->save();
            /*return response()->json([
                'message' => 'Successfully restored Cycle Count Status.',
            ], 200);*/
            return $this->responseSuccess('Successfully restored Cycle Count Status.');
        }
    }
}
