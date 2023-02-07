<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MinorCategory;
use App\Http\Requests\MinorCategory\MinorCategoryRequest;

class MinorCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(MinorCategoryRequest $request)
    {
        $minor_cat_name = $request->minor_category_name;
        $urgency_level = $request->urgency_level;
        $personally_assign = $request->personally_assign;
        $evaluate_in_every_movement = $request->evaluate_in_every_movement;
        $create = MinorCategory::create([
            'minor_category_name' => $minor_cat_name,
            'urgency_level' => $urgency_level,
            'personally_assign' => $personally_assign,
            'evaluate_in_every_movement' => $evaluate_in_every_movement,
            'is_active' => 1
        ]);

        return response()->json(['message' => 'Successfully Created', 'data' => $create ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(MinorCategoryRequest $request,$id)
    {
       $MinorCategory = MinorCategory::where('id', $id)->first();
       return $MinorCategory;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(MinorCategoryRequest $request, $id)
    {
        $minor_category_name = $request->minor_category_name;
        $urgency_level = $request->urgency_level;
        $personally_assign = $request->personally_assign;
        $evaluate_in_every_movement = $request->evaluate_in_every_movement;
        // if(!MinorCategory::where('id', $id)->exists()){
        //     return response()->json(['message' => 'The given data was invalid.', 'errors' => ['id'=> 'The selected minor category id is invalid']],422);
        // }
        if(MinorCategory::where('id',$id)
        ->where([
            'minor_category_name' => $minor_category_name,
            'urgency_level' => $urgency_level,
            'personally_assign' => $personally_assign,
            'evaluate_in_every_movement' => $evaluate_in_every_movement,

        ])
        ->exists()){
            return response()->json(['message' => 'No Changes'], 200);
        }
        $update = MinorCategory::where('id', $id)
        ->update([
            'minor_category_name' => $minor_category_name,
            'urgency_level' => $urgency_level,
            'personally_assign' => $personally_assign,
            'evaluate_in_every_movement' => $evaluate_in_every_movement
        ]);
        return response()->json(['message' => 'Successfully Updated!'], 200);
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


    public function archived(MinorCategoryRequest $request, $id){

        $status = $request->status; 
        $MinorCategory = MinorCategory::query();
        if(!$MinorCategory->withTrashed()->where('id', $id)->exists()){
            return response()->json(['message' => 'The given data was invalid.', 'errors' => ['id'=>['Minor CategoryNot Found']]], 404);
        }

        if($status == false){
            if(!MinorCategory::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{
                $updateStatus = $MinorCategory->where('id', $id)->update(['is_active' => false]);
                $MinorCategory->where('id',$id)->delete();
                return response()->json(['message' => 'Minor Category Successfully Deactived!'], 200);
            }
        }
        if($status == true){
            if(MinorCategory::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{              
                $restoreUser = $MinorCategory->withTrashed()->where('id',$id)->restore();
                $updateStatus = $MinorCategory->update(['is_active' => true]); 
                return response()->json(['message' => 'Minor Category Successfully Activated!'], 200);

            }

        }

    }
}
