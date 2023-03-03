<?php

namespace App\Http\Controllers;

use App\Models\CategoryList;
use Illuminate\Http\Request;
use App\Models\MajorCategory;
use App\Http\Requests\MajorCategory\MajorCategoryRequest;

class MajorCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $major_category = MajorCategory::get();
        return $major_category;
    
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(MajorCategoryRequest $request)
    {
        $major_category_name = $request->major_category_name;
        $classification = $request->classification;
        $MajorCategory = MajorCategory::query();
        $create = $MajorCategory->create([
            "major_category_name" => $major_category_name,
            "classification" => $classification,
            "is_active" => 1
        ]);
        
        return response()->json(['message' => 'Successfully Created!', 'data' => $create]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(MajorCategoryRequest $request,$id)
    {
        $MajorCategory = MajorCategory::query();
        if(!$MajorCategory->where('id', $id)->exists()){
            return response()->json(['error' => 'Major Category Route Not Found'], 404);
        }
       return $MajorCategory->where('id', $id)->first();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(MajorCategoryRequest $request,$id)
    {
        $major_category_name = $request->major_category_name;
        $classification = $request->classification;
        if(!MajorCategory::where('id', $id)->exists()){
            return response()->json(['error' => 'Major Category Route Not Found'], 404);
        }
        if(MajorCategory::where('id',$id)->where('major_category_name', $major_category_name)->exists()){
            return response()->json(['message' => 'No Changes'], 200);
        }
        $update = MajorCategory::where('id', $id)
        ->update([
            'major_category_name' => $major_category_name,
            'classification' => $classification
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


    public function archived(MajorCategoryRequest $request, $id){

        $status = $request->status; 
        $MajorCategory = MajorCategory::query();
        if(!$MajorCategory->withTrashed()->where('id', $id)->exists()){
            return response()->json(['error' => 'Major Category Route Not Found'], 404);
        } 

        if(CategoryList::where('major_category', $id)->exists()){
            if($status == true){
                return response()->json(['message' => 'No Changes'],200);
            }
            else{
                return response()->json(['message' => 'Unable to Archived!'],409);
            }
        }

        if($status == false){
            if(!MajorCategory::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{
                if(!CategoryList::where('major_category_id', $id)->exists()){
                    $updateStatus = $MajorCategory->where('id', $id)->update(['is_active' => false]);
                    $MajorCategory->where('id',$id)->delete();
                    return response()->json(['message' => 'Successfully Deactived!'], 200);
                }
                return response()->json(['message' => 'Unable to Archived!, Major Category was tagged!']);
            }
        }
        if($status == true){
            if(MajorCategory::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{              
                $restoreUser = $MajorCategory->withTrashed()->where('id',$id)->restore();
                $updateStatus = $MajorCategory->update(['is_active' => true]); 
                return response()->json(['message' => 'Successfully Activated!'], 200);

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
        $MajorCategory = MajorCategory::withTrashed()
        ->where(function($query) use($status){
            $query->where('is_active', $status);
        })
        ->where(function($query) use($search){
            $query->where('major_category_name', 'LIKE', "%{$search}%" );
        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        return $MajorCategory;
    }


}
