<?php

namespace App\Http\Controllers\Masterlist\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\MovementStatus\MovementStatusRequest;
use App\Models\FixedAsset;
use App\Models\Status\MovementStatus;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class MovementStatusController extends Controller
{

    use ApiResponse;


    public function index(Request $request)
    {
        $movementStatus  = $request->status ?? 'status';
        $isActiveStatus = ($movementStatus === 'deactivated') ? 0 : 1;

        $movementStatus = MovementStatus::withTrashed()->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPagination();

        return $movementStatus;
    }

    public function store(MovementStatusRequest $request)
    {
        $movement_status_name = ucwords(strtolower($request->movement_status_name));

        $movementStatus = MovementStatus::create([
            'movement_status_name' => $movement_status_name
        ]);

        /*return response()->json([
            'message' => 'Successfully created movement status.',
            'data' => $movementStatus
        ], 201);*/

        return $this->responseCreated('Successfully created movement status.', $movementStatus);
    }

    public function show($id)
    {
        $movementStatus = MovementStatus::withTrashed()->find($id);
        if (!$movementStatus) {
            /*return response()->json([
                'error' => 'Movement status not found.'
            ], 404);*/
            return $this->responseNotFound('Movement status not found.');
        }

        return response()->json([
            'message' => 'Successfully retrieved movement status.',
            'data' => $movementStatus
        ], 200);
    }

    public function update(MovementStatusRequest $request, $id)
    {
        $movement_status_name = ucwords(strtolower($request->movement_status_name));

        $movementStatus = MovementStatus::withTrashed()->find($id);
        if (!$movementStatus) return $this->responseNotFound('Movement status not found.');

        if ($movementStatus->movement_status_name == $movement_status_name) {
            /*return response()->json([
                'message' => 'No changes were made.'
            ], 200);*/
            return $this->responseSuccess('No changes were made.');
        }

        $movementStatus->update([
            'movement_status_name' => $movement_status_name
        ]);

//        return response()->json([
//            'message' => 'Successfully updated movement status.',
//            'data' => $movementStatus
//        ], 200);

        return $this->responseSuccess('Successfully updated movement status.');
    }

    public function archived(MovementStatusRequest $request, $id)
    {
        $status = $request->status;

        $movementStatus = MovementStatus::withTrashed()->find($id);

        if (!$movementStatus) {
//            return response()->json([
//                'message' => 'Movement Status Route Not Found.'
//            ], 404);
            return $this->responseNotFound('Movement Status Route Not Found.');
        }


        if ($status == $movementStatus->is_active) {
//            return response()->json([
//                'message' => 'No Changes.'
//            ], 200);
            return $this->responseSuccess('No Changes.');
        }


        if (!$status) {

            $checkFixedAsset = FixedAsset::where('movement_status_id', $id)->exists();
            if ($checkFixedAsset) {
                /*return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'movement_status' => [
                            'Movement Status is still in use.'
                        ]
                    ]
                ], 422);*/
                return $this->responseUnprocessableEntity('Movement Status is still in use.');
            }

            $movementStatus->is_active = false;
            $movementStatus->save();
            $movementStatus->delete();
            /*return response()->json([
                'message' => 'Successfully archived Movement Status.',
            ], 200);*/
            return $this->responseSuccess('Successfully archived Movement Status.');
        } else {
            $movementStatus->restore();
            $movementStatus->is_active = true;
            $movementStatus->save();
            /*return response()->json([
                'message' => 'Successfully restored Movement Status.',
            ], 200);*/
            return $this->responseSuccess('Successfully restored Movement Status.');
        }
    }
}
