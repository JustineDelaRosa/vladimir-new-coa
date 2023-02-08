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
        $MinorCategory = MinorCategory::get();
        return $MinorCategory;
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
    public function show($id)
    {  
       $MinorCategory = MinorCategory::query();
       if(!$MinorCategory->where('id', $id)->exists()){
        return response()->json(['error' => 'Minor Category Route Not Found'], 404);
       }
       return $MinorCategory->where('id', $id)->first();
      
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
        if(!MinorCategory::where('id', $id)->exists()){
            return response()->json(['error' => 'Minor Category Route Not Found'], 404);
        }
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
            return response()->json(['error' => 'Minor Category Route Not Found'], 404);
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

    public function search(Request $request){
        $search = $request->query('search');
        $limit = $request->query('limit');
        $page = $request->get('page');
        $status = $request->query('status');
        if($status == NULL ){
            $status = 1;
        }
        if($status == "active"){
            $status = 1;
        }
        if($status == "deactivated"){
            $status = 0;
        }
        if($status != "active" || $status != "deactivated"){
            $status = 1;
        }
        $MinorCategory = MinorCategory::withTrashed()
        ->where(function($query) use($status){
            $query->where('is_active', $status);
        })
        ->where(function($query) use($search){
            $query->where('minor_category_name', 'LIKE', "%{$search}%" )
            ->where('urgency_level', 'LIKE', "%{$search}%");
        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        return $MinorCategory;
    }

}
