<?php

namespace App\Http\Controllers;

use App\Http\Requests\TypeOfRequest\TypeOfRequestRequest;
use App\Models\FixedAsset;
use App\Models\TypeOfRequest;
use Illuminate\Http\Request;

class TypeOfRequestController extends Controller
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

        $typeOfRequest = TypeOfRequest::where(function ($query) use ($search) {
            $query
                ->where("type_of_request_name", "like", "%" . $search . "%");
        })
            ->when($status === "deactivated", function ($query) {
                $query->onlyTrashed();
            })
            ->orderByDesc("updated_at");
        $typeOfRequest = $limit ? $typeOfRequest->paginate($limit) : $typeOfRequest->get();


        return response()->json([
            'message' => 'Successfully retrieved Type Of Request.',
            'data' => $typeOfRequest
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(TypeOfRequestRequest $request)
    {
        $type_of_request_name = ucwords(strtolower($request->type_of_request_name));
        $typeOfRequest = TypeOfRequest::create([
            'type_of_request_name' => $type_of_request_name,
            'is_active' => true
        ]);
        return response()->json([
            'message' => 'Successfully created Type Of Request.',
            'data' => $typeOfRequest
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
        $typeofrequest = TypeOfRequest::where('id', $id)->first();
        return response()->json([
            'data' => $typeofrequest,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(TypeOfRequestRequest $request, $id)
    {
        $type_of_request_name = ucwords(strtolower($request->type_of_request_name));

        if(TypeOfRequest::where('id',$id)->where([
            'type_of_request_name' => $type_of_request_name,
        ])->exists()){
            return response()->json([
                'message' => 'No changes.',
            ], 200);
        }


        if(TypeOfRequest::where('id', $id)->exists()){
            $updateTor = TypeOfRequest::Where('id', $id)->update([
                'type_of_request_name' => $type_of_request_name,
            ]);
            return response()->json([
                'message' => 'Successfully updated Type of Request.',
            ], 200);
        }else{
            return response()->json([
                'message' => 'Type of Request Route Not Found.'
            ], 404);
        }
    }
    public function archived(TypeOfRequestRequest $request, $id)
    {

        $status = $request->status;

        $typeOfRequest = TypeOfRequest::query();
        if(!$typeOfRequest->withTrashed()->where('id', $id)->exists()){
            return response()->json([
                'message' => 'Type Of Request Route Not Found.'
            ], 404);
        }

        if($status == false){
            if(!TypeOfRequest::where('id', $id)->where('is_active', true)->exists()){
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            }else{
                $checkFixedAsset = FixedAsset::where('type_of_request_id', $id)->exists();
                if ($checkFixedAsset) {
                    return response()->json(['error' => 'Unable to archived , Type Of Request is still in use!'], 409);
                }
                if(TypeOfRequest::where('id', $id)->exists()){
                    $updateStatus= TypeOfRequest::Where('id', $id)->update([
                        'is_active' => false,
                    ]);
                    $archiveTOR = TypeOfRequest::where('id', $id)->delete();
                    return response()->json([
                        'message' => 'Successfully archived Type Of Request.',
                    ], 200);
                }

            }
        }

        if($status == true){
            if(TypeOfRequest::where('id', $id)->where('is_active', true)->exists()){
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            }else{
                $restoreTOR = TypeOfRequest::withTrashed()->where('id', $id)->restore();
                $updateStatus = TypeOfRequest::where('id', $id)->update([
                    'is_active' => true,
                ]);
                return response()->json([
                    'message' => 'Successfully restored Type Of Request.',
                ], 200);
            }
        }
    }
}
