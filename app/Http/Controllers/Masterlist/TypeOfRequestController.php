<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\TypeOfRequest\TypeOfRequestRequest;
use App\Models\FixedAsset;
use App\Models\TypeOfRequest;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class TypeOfRequestController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {

        $typeOfRequestStatus = $request->status ?? 'active';
        $isActiveStatus =($typeOfRequestStatus === 'deactivated') ? 0 : 1;

        $typeOfRequest = TypeOfRequest::withTrashed()->where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPaginate();


        return $typeOfRequest;
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
//        return response()->json([
//            'message' => 'Successfully created Type Of Request.',
//            'data' => $typeOfRequest
//        ], 201);

        return $this->responseCreated('Successfully created Type Of Request.');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $typeOfRequest = TypeOfRequest::find($id);
        if (!$typeOfRequest) {
            return $this->responseNotFound('Type of Request Route Not Found.');
        }
        return $typeOfRequest;
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

        if (TypeOfRequest::where('id', $id)->where([
            'type_of_request_name' => $type_of_request_name,
        ])->exists()) {
//            return response()->json([
//                'message' => 'No changes.',
//            ], 200);

            return $this->responseSuccess('No changes.');
        }


        if (TypeOfRequest::where('id', $id)->exists()) {
            $updateTor = TypeOfRequest::Where('id', $id)->update([
                'type_of_request_name' => $type_of_request_name,
            ]);
//            return response()->json([
//                'message' => 'Successfully updated Type of Request.',
//            ], 200);
            return $this->responseSuccess('Successfully updated Type of Request.');

        } else {
//            return response()->json([
//                'message' => 'Type of Request Route Not Found.'
//            ], 404);

            return $this->responseNotFound('Type of Request Route Not Found.');
        }
    }

    public function archived(TypeOfRequestRequest $request, $id)
    {

        $status = $request->status;

        $typeOfRequest = TypeOfRequest::query();
        if (!$typeOfRequest->withTrashed()->where('id', $id)->exists()) {
//            return response()->json([
//                'message' => 'Type Of Request Route Not Found.'
//            ], 404);
            return $this->responseNotFound('Type Of Request Route Not Found.');
        }

        if ($status == false) {
            if (!TypeOfRequest::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json([
//                    'message' => 'No Changes.'
//                ], 200);
                return $this->responseSuccess('No Changes.');
            } else {
                $checkFixedAsset = FixedAsset::where('type_of_request_id', $id)->exists();
                if ($checkFixedAsset) {
//                    return response()->json(['error' => 'Unable to archived , Type Of Request is still in use!'], 422);
                    return $this->responseUnprocessable('Unable to archived , Type Of Request is still in use!');
                }
                if (TypeOfRequest::where('id', $id)->exists()) {
                    $updateStatus = TypeOfRequest::Where('id', $id)->update([
                        'is_active' => false,
                    ]);
                    $archiveTOR = TypeOfRequest::where('id', $id)->delete();
//                    return response()->json([
//                        'message' => 'Successfully archived Type Of Request.',
//                    ], 200);
                    return $this->responseSuccess('Successfully archived Type Of Request.');
                }

            }
        }

        if ($status == true) {
            if (TypeOfRequest::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json([
//                    'message' => 'No Changes.'
//                ], 200);
                return $this->responseSuccess('No Changes.');
            } else {
                $restoreTOR = TypeOfRequest::withTrashed()->where('id', $id)->restore();
                $updateStatus = TypeOfRequest::where('id', $id)->update([
                    'is_active' => true,
                ]);
//                return response()->json([
//                    'message' => 'Successfully restored Type Of Request.',
//                ], 200);
                return $this->responseSuccess('Successfully restored Type Of Request.');
            }
        }
    }
}
