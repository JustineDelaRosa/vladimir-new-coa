<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Capex\SubCapexRequest;
use App\Models\Capex;
use App\Models\FixedAsset;
use App\Models\SubCapex;
use Illuminate\Http\Request;

class SubCapexController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $capexStatus = $request->status ?? 'active';
        $isActiveStatus = ($capexStatus === 'deactivated') ? 0 : 1;

        $subCapex = SubCapex::withTrashed()->where('is_active', $isActiveStatus)
        ->orderByDesc('created_at')
        ->useFilters()
        ->dynamicPaginate();


        return $subCapex;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(SubCapexRequest $request)
    {
        $capex_id = $request->capex_id;
        $sub_capex = strtoupper($request->sub_capex);
        $sub_project = ucwords(strtolower($request->sub_project));
        $capex = Capex::with('subCapex')->where('id', $capex_id)->first();
        //check if this capex has this sub capex already
        if (!$capex) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'capex_id' => [
                        'Capex does not exists.'
                    ]
                ]
            ], 422);
        }

        //check also the soft deleted
        $subCapexName = $capex->capex . '-' . $sub_capex;
        $subCapexCheck = $capex->subCapex()->withTrashed()->where('sub_capex', $subCapexName)->first();
        if ($subCapexCheck) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'sub_capex' => [
                        'Already exists'
                    ]
                ]
            ], 422);
        }

        $capex->subCapex()->create([
            'sub_capex' => $capex->capex . '-' . $sub_capex,
            'sub_project' => $sub_project,
            'is_active' => true
        ]);

        return response()->json([
            'message' => 'Successfully created sub capex.',
            'data' => $capex->subCapex()->latest()->first()
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
        $sub_capex = SubCapex::with('capex')->find($id);
        if (!$sub_capex) {
            return response()->json([
                'error' => 'Sub Capex route not found.'
            ], 404);
        }
        $sub_capex->sub_capex = explode('-', $sub_capex->sub_capex)[2];

        return response()->json([
            'message' => 'Successfully retrieved sub capex.',
            'data' => $sub_capex
        ], 200);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(SubCapexRequest $request, $id)
    {
        $sub_project = ucwords(strtolower($request->sub_project));
        $sub_capex = SubCapex::find($id);
        if (!$sub_capex) {
            return response()->json([
                'error' => 'Sub Capex route not found.'
            ], 404);
        }

        $sub_capex->update([
            'sub_project' => $sub_project
        ]);

        return response()->json([
            'message' => 'Successfully updated sub capex.',
            'data' => $sub_capex
        ], 200);
    }


    public function archived(SubCapexRequest $request, $id)
    {
        $status = $request->status;

        $sub_capex = SubCapex::query();
        if (!$sub_capex->withTrashed()->where('id', $id)->exists()) {
            return response()->json([
                'error' => 'SubCapex Route Not Found.'
            ], 404);
        }

        if ($status == false) {
            if (!SubCapex::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            } else {
                $checkFixedAsset = FixedAsset::where('sub_capex_id', $id)->exists();
                if ($checkFixedAsset) {
                    return response()->json(['error' => 'SubCapex is still in use!'], 422);
                }
                if (SubCapex::where('id', $id)->exists()) {
                    $updateCapex = SubCapex::Where('id', $id)->update([
                        'is_active' => false,
                    ]);
                    $archiveCapex = SubCapex::where('id', $id)->delete();
                    return response()->json([
                        'message' => 'Successfully archived SubCapex.',
                    ], 200);
                }

            }
        }

        if ($status == true) {
            if (SubCapex::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            } else {
                $capex_id = SubCapex::withTrashed()->where('id', $id)->first()->capex_id;
                if (Capex::onlyTrashed()->where('id', $capex_id)->exists()) {
                    return response()->json([
                        'error' => 'Unable to restore, Capex is in archived!'
                    ], 422);
                }
                $restoreCapex = SubCapex::withTrashed()->where('id', $id)->restore();
                $updateStatus = SubCapex::where('id', $id)->update([
                    'is_active' => true,
                ]);
                return response()->json([
                    'message' => 'Successfully restored SubCapex.',
                ], 200);
            }
        }
    }
}
