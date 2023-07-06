<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use App\Http\Requests\Division\DivisionRequest;
use App\Models\MajorCategory;
use Illuminate\Support\Facades\DB;

class DivisionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $division = Division::get();
        return response()->json([
            'data' => $division
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(DivisionRequest $request)
    {
        //capitalized
        $division_name = ucwords(strtolower($request->division_name));
        //get the array of department ids
        $department_sync_id = $request->department_sync_id;


        Division::create([
            'division_name' => $division_name,
            'is_active' => 1
        ]);
        $division_id = Division::where('division_name', $division_name)->first()->id;
        if($division_id){
            foreach ($department_sync_id as $id) {
                //add the division id to department table with the division id column
                Department::where('sync_id', $id)->update([
                    'division_id' => $division_id
                ]);
            }
        }else{
            return response()->json(['error' => 'Division Route Not Found'], 404);
        }
        return response()->json([
            'message' => 'Successfully Created!',
            'data' => Division::where('division_name', $division_name)->first()
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
        $division = Division::query();
        if (!$division->where('id', $id)->exists()) {
            return response()->json(['error' => 'Division Route Not Found'], 404);
        }
        return $division->where('id', $id)->first();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(DivisionRequest $request, $id)
    {
        //capitalized first letter per word doesnt allow capitalization of all letters
        $division_name = ucwords(strtolower($request->division_name));
        $department_sync_id = $request->department_id;

        if (!Division::where('id', $id)->exists()) {
            return response()->json(['error' => 'Division Route Not Found'], 404);
        }

//        $department = Department::where('division_id', $id)->get();
//        $department_sync_id_array = [];
//        foreach ($department as $dept) {
//            array_push($department_sync_id_array, $dept->sync_id);
//        }
//        //check also if division name doesnt change
//        if (Division::where('id', $id)->where('division_name', $division_name)->exists() && $department_sync_id == $department_sync_id_array) {
//            return response()->json(['message' => 'No Changes Made'], 200);
//        }

        //use pluck method to get an array of sync_id values
        $department_sync_id_array = Department::where('division_id', $id)->pluck('sync_id')->toArray();
        //use firstWhere method to get the division with the given id and name
        $division = Division::firstWhere(['id' => $id, 'division_name' => $division_name]);
        //check if division exists and department_id matches the array
        if ($division && $department_sync_id == $department_sync_id_array) {
            return response()->json(['message' => 'No Changes Made'], 200);
        }

        $update = Division::where('id', $id)->update([
            'division_name' => $division_name,
        ]);

//        //null the division id in department table then update the department id with the new division id
//        Department::where('division_id', $id)->update([
//            'division_id' => null
//        ]);
//        foreach ($department_sync_id as $dept_id) {
//            Department::where('id', $dept_id)->update([
//                'division_id' => $id
//            ]);
//        }

        if($update){
            Department::where('division_id', $id)->whereNotIn('sync_id', $department_sync_id)->update([
                'division_id' => null
            ]);
            Department::whereIn('sync_id', $department_sync_id)->update([
                'division_id' => $id
            ]);
            return response()->json(['message' => 'Successfully Updated!', 'data' => Division::where('id', $id)->first()], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function archived(Request $request, $id)
    {

        $status = $request->status;
        $Division = Division::query();
        if (!$Division->withTrashed()->where('id', $id)->exists()) {
            return response()->json(['error' => 'Division Route Not Found'], 404);
        }


        if ($status == false) {
            if (!Division::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $checkFixedAsset = FixedAsset::where('division_id', $id)->exists();
                if ($checkFixedAsset) {
                    return response()->json(['error' => 'Unable to archived , Division is still in use!'], 422);
                }

                $updateStatus = $Division->where('id', $id)->update(['is_active' => false]);
                $Division->where('id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactived!'], 200);
            }
        }
        if ($status == true) {
            if (Division::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $restoreUser = $Division->withTrashed()->where('id', $id)->restore();
                $updateStatus = $Division->update(['is_active' => true]);
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
        }
    }

    public function search(Request $request)
    {
        $search = $request->query('search');
        $limit = $request->query('limit');
        $page = $request->get('page');
        $status = $request->query('status');
        if ($status == NULL) {
            $status = 1;
        }
        if ($status == "active") {
            $status = 1;
        }
        if ($status == "deactivated") {
            $status = 0;
        }
        if ($status != "active" || $status != "deactivated") {
            $status = 1;
        }
        $Division = Division::withTrashed()
            ->where(function ($query) use ($status) {
                $query->where('is_active', $status);
            })
            ->where(function ($query) use ($search) {
                $query->where('division_name', 'LIKE', "%{$search}%");
            })
            ->orderby('created_at', 'DESC')
            ->paginate($limit);
        return $Division;
    }
}
