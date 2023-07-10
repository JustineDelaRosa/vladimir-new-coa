<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Capex\CapexRequest;
use App\Http\Requests\Capex\SubCapexRequest;
use App\Models\Capex;
use App\Models\FixedAsset;
use App\Models\SubCapex;
use Illuminate\Http\Request;

class CapexController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $search = $request->search;
        $status = $request->status;
        $limit = $request->limit;

        $capex = Capex::with([
                'subCapex' => function ($query) {
                    $query->withTrashed();
                },
            ]
        )
            ->where(function ($query) use ($search) {
            $query
                ->where("capex", "like", "%" . $search . "%")
                ->orWhere("project_name", "like", "%" . $search . "%");
        })
            ->when($status === "deactivated", function ($query) {
                $query->onlyTrashed();
            })
            ->orderByDesc("updated_at");
        $capex = $limit ? $capex->paginate($limit) : $capex->get();

        return response()->json([
            'message' => 'Successfully retrieved capex.',
            'data' => $capex
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CapexRequest $request)
    {
        $capex = $request->capex;
        $project_name = ucwords(strtolower($request->project_name));
        $sub_capex = $request->sub_capex ?? $capex;
        $sub_project = ucwords(strtolower($request->sub_project ?? $project_name));
        $capex = Capex::create([
            'capex' => $capex,
            'project_name' => $project_name,
            'sub_capex' => $sub_capex,
            'sub_project' => $sub_project,
            'is_active' => true
        ]);
        return response()->json([
            'message' => 'Successfully created capex.',
            'data' => $capex
        ], 201);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $capex = Capex::query();
        if(!$capex->where('id', $id)->exists()){
            return response()->json([
                'error' => 'Capex Route Not Found.'
            ], 404);
        }
        $capex = $capex->where('id', $id)->first();
        return response()->json([
            'message' => 'Successfully retrieved capex.',
            'data' => $capex
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CapexRequest $request, $id)
    {
        $project_name = ucwords(strtolower($request->project_name));
        $sub_project = $request->sub_project;

        if(Capex::where('id',$id)->where([
            'project_name' => $project_name,
             'sub_project' => $sub_project
        ])->exists()){
            return response()->json([
                'message' => 'No changes.',
            ], 200);
        }

        $capex = Capex::where('id', $id)->first();
        if ($capex) {
            $updateCapex = Capex::Where('id', $id)->update([
                'project_name' => $project_name,
                'sub_project' => ($capex->capex == $capex->sub_capex) ? $project_name : $sub_project,
            ]);
            return response()->json([
                'message' => 'Successfully updated capex.',
            ], 200);
        } else {
            return response()->json([
                'error' => 'Capex Route Not Found.'
            ], 404);
        }
    }

    public function archived(CapexRequest $request, $id)
    {

        $status = $request->status;

        $capex = Capex::query();
        if(!$capex->withTrashed()->where('id', $id)->exists()){
            return response()->json([
                'error' => 'Capex Route Not Found.'
            ], 404);
        }

        if($status == false){
            if(!Capex::where('id', $id)->where('is_active', true)->exists()){
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            }else{
                $checkFixedAsset = FixedAsset::where('capex_id', $id)->exists();
                if ($checkFixedAsset) {
                    return response()->json(['error' => 'Unable to archived , Capex is still in use!'], 422);
                }
                if(Capex::where('id', $id)->exists()){

                    $sub_capex_check = SubCapex::where('capex_id', $id)->get('id');
                    //check if any of the sub capex is in use by fixed asset
                    $checkFixedAsset = FixedAsset::whereIn('capex_id',$sub_capex_check)->exists();
                    if ($checkFixedAsset) {
                        return response()->json(['error' => 'Unable to archive, Sub Capex is still in use!'], 422);
                    }

                    $updateCapex = Capex::Where('id', $id)->update([
                        'is_active' => false,
                    ]);
                    //change also the status of sub capex
                    $updateSubCapex = SubCapex::whereIn('id', $sub_capex_check)->update([
                        'is_active' => false,
                    ]);
                    $archiveCapex = Capex::where('id', $id)->delete();
                    $archiveSubCapex = SubCapex::whereIn('id', $sub_capex_check)->delete();
                    return response()->json([
                        'message' => 'Successfully archived capex.',
                    ], 200);
                }

            }
        }

        if($status == true){
            if(Capex::where('id', $id)->where('is_active', true)->exists()){
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            }else{
                $restoreCapex = Capex::withTrashed()->where('id', $id)->restore();
                $updateStatus = Capex::where('id', $id)->update([
                    'is_active' => true,
                ]);
                return response()->json([
                    'message' => 'Successfully restored capex.',
                ], 200);
            }
        }
    }
}
