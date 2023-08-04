<?php

namespace App\Http\Controllers\Masterlist\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\MovementStatus\MovementStatusRequest;
use App\Models\FixedAsset;
use App\Models\Status\MovementStatus;
use Illuminate\Http\Request;

class MovementStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->search;
        $status = $request->status;
        $limit = $request->limit;

        $movementStatus = MovementStatus::where(function ($query) use ($search) {
            $query
                ->where("movement_status_name", "like", "%" . $search . "%");
        })
            ->when($request->status === 'deactivated', function ($query) {
                return $query->onlyTrashed();
            })
            ->when($request->status === 'active', function ($query) {
                return $query->whereNull('deleted_at');
            })
            ->orderByDesc('created_at')
            ->when($request->limit, function ($query) use ($request) {
                return $query->paginate($request->limit);
            }, function ($query) {
                return $query->get();
            });

        return $movementStatus;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(MovementStatusRequest $request)
    {
        $movement_status_name = ucwords(strtolower($request->movement_status_name));

        $movementStatus = MovementStatus::create([
            'movement_status_name' => $movement_status_name
        ]);

        return response()->json([
            'message' => 'Successfully created movement status.',
            'data' => $movementStatus
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $movementStatus = MovementStatus::withTrashed()->find($id);
        if (!$movementStatus) {
            return response()->json([
                'error' => 'Movement status not found.'
            ], 404);
        }

        return response()->json([
            'message' => 'Successfully retrieved movement status.',
            'data' => $movementStatus
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(MovementStatusRequest $request, $id)
    {
        $movement_status_name = ucwords(strtolower($request->movement_status_name));

        $movementStatus = MovementStatus::withTrashed()->find($id);
        if (!$movementStatus) {
            return response()->json([
                'error' => 'Movement status not found.'
            ], 404);
        }

        if ($movementStatus->movement_status_name == $movement_status_name) {
            return response()->json([
                'message' => 'No changes were made.'
            ], 200);
        }

        $movementStatus->update([
            'movement_status_name' => $movement_status_name
        ]);

        return response()->json([
            'message' => 'Successfully updated movement status.',
            'data' => $movementStatus
        ], 200);
    }

    public function archived(MovementStatusRequest $request, $id)
    {
        $status = $request->status;

        $movementStatus = MovementStatus::withTrashed()->find($id);

        if (!$movementStatus) {
            return response()->json([
                'message' => 'Movement Status Route Not Found.'
            ], 404);
        }


        if ($status == $movementStatus->is_active) {
            return response()->json([
                'message' => 'No Changes.'
            ], 200);
        }


        if (!$status) {

            $checkFixedAsset = FixedAsset::where('movement_status_id', $id)->exists();
            if ($checkFixedAsset) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'movement_status' => [
                            'Movement Status is still in use.'
                        ]
                    ]
                ], 422);
            }

            $movementStatus->is_active = false;
            $movementStatus->save();
            $movementStatus->delete();
            return response()->json([
                'message' => 'Successfully archived Movement Status.',
            ], 200);
        } else {
            $movementStatus->restore();
            $movementStatus->is_active = true;
            $movementStatus->save();
            return response()->json([
                'message' => 'Successfully restored Movement Status.',
            ], 200);
        }
    }
}
