<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdditionalCost\AdditionalCostRequest;
use App\Models\AdditionalCost;
use App\Models\Department;
use App\Repositories\AdditionalCostRepository;
use Illuminate\Http\Request;

class AdditionalCostController extends Controller
{
    protected $additionalCostRepository;

    public function __construct()
    {
        $this->additionalCostRepository = new AdditionalCostRepository();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $additionalCost = AdditionalCost::with('formula')->get();
        return response()->json([
            'message' => 'Successfully fetched all additional cost!',
            'data' => $this->additionalCostRepository->transformAdditionalCost($additionalCost)
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AdditionalCostRequest $request)
    {
        $departmentQuery = Department::where('id', $request->department_id)->first();
        $additionalCost = $this->additionalCostRepository->storeAdditionalCost($request->all(), $departmentQuery);

        return response()->json([
            'message' => 'Additional Cost successfully created!',
            'data' => $additionalCost,
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
        $additional_cost = AdditionalCost::withTrashed()->with('formula')->where('id', $id)->first();

        if(!$additional_cost) {
            return response()->json([
                'message' => 'Additional Cost route not found!',
            ], 404);
        }

        return response()->json([
            'message' => 'Successfully fetched additional cost!',
            'data' => $this->additionalCostRepository->transformSingleAdditionalCost($additional_cost),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AdditionalCostRequest $request, $id)
    {
        $request->validated();
        $departmentQuery = Department::where('id', $request->department_id)->first();
        $additionalCost = AdditionalCost::where('id', $id)->first();
        if($additionalCost) {
            $additionalCost = $this->additionalCostRepository->updateAdditionalCost($request->all(), $departmentQuery, $id);
            return response()->json([
                'message' => 'Additional Cost successfully updated!',
                'data' => $additionalCost->load('formula'),
            ], 201);
        }else{
            return response()->json([
                'message' => 'Additional Cost not found!',
            ], 404);
        }
    }

}
