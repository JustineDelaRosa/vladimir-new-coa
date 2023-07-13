<?php

namespace App\Http\Controllers\Masterlist\Status;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\DepreciationStatus\DepreciationStatusRequest;
use App\Models\Status\DepreciationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepreciationStatusController extends Controller
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

        $depreciationStatus = DepreciationStatus::withTrashed()->where(function ($query) use ($search) {
            $query
                ->where("depreciation_status_name", "like", "%" . $search . "%");
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

        return $depreciationStatus;

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(DepreciationStatusRequest $request)
    {
        $depreciation_status_name = ucwords(strtolower($request->depreciation_status_name));

        $depreciationStatus = DepreciationStatus::create([
            'depreciation_status_name' => $depreciation_status_name
        ]);

        return response()->json([
            'message' => 'Successfully created depreciation status.',
            'data' => $depreciationStatus
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
       $depreciationStatus = DepreciationStatus::find($id);
       if(!$depreciationStatus) return response()->json([
           'error' => 'Depreciation status route not found.'
         ], 404);

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
        if(!$depreciationStatus) return response()->json([
            'error' => 'Depreciation status route not found.'
        ], 404);

        //check if no changes
        if($depreciationStatus->depreciation_status_name == $depreciation_status_name){
            return response()->json([
                'message' => 'No changes were made.'
            ], 200);
        }

        $depreciationStatus->update([
            'depreciation_status_name' => $depreciation_status_name
        ]);

        return response()->json([
            'message' => 'Successfully updated depreciation status.',
            'data' => $depreciationStatus
        ], 200);
    }


    public function archived(DepreciationStatusRequest $request, $id)
    {
        $status = $request->status;

        $depreciationStatus = DepreciationStatus::withTrashed()->find($id);

        if (!$depreciationStatus) {
            return response()->json([
                'message' => 'Depreciation Status Route Not Found.'
            ], 404);
        }


        if($status == $depreciationStatus->is_active){
            return response()->json([
                'message' => 'No Changes.'
            ], 200);
        }


        if(!$status){
            $depreciationStatus->is_active = false;
            $depreciationStatus->save();
            $depreciationStatus->delete();
            return response()->json([
                'message' => 'Successfully archived Depreciation Status.',
            ], 200);
        }else{
            $depreciationStatus->restore();
            $depreciationStatus->is_active = true;
            $depreciationStatus->save();
            return response()->json([
                'message' => 'Successfully restored Depreciation Status.',
            ], 200);
        }
    }
}
