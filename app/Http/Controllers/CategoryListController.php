<?php

namespace App\Http\Controllers;

use App\Models\CategoryList;
use Illuminate\Http\Request;
use App\Models\MinorCategory;
use App\Models\CategoryListTagMinorCategory;
use App\Http\Requests\CategoryList\CategoryListRequest;

class CategoryListController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $CategoryList = CategoryList::with('serviceProvider')
        ->with('majorCategory')
        ->with('categoryListTag.minorCategory')
        ->get();
        return $CategoryList;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CategoryListRequest $request)
    {
        $service_provider_id = $request->service_provider_id;
        $major_category_id = $request->major_category_id;
        $minor_category_id = array_unique($request->minor_category_id);
        $create = [];
        $categoryList = CategoryList::create([
            'service_provider_id' => $service_provider_id,
            'major_category_id' => $major_category_id,
            'is_active' => 1
        ]);

        foreach($minor_category_id as $minor){
            $MinorCategory = MinorCategory::find($minor);
            if($MinorCategory){
                $tagMinor = CategoryListTagMinorCategory::create([
                    'category_list_id' => $categoryList->id,
                    'minor_category_id' => $minor,
                    'is_active' => 1
                ]);
                $getMinorCategory = MinorCategory::where('id', $minor)->first();
                array_push($create, $getMinorCategory);
            }
        }
        return response()->json(['message' => 'Successfully Create', 'data' => $create], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $CategoryList = CategoryList::find($id);
        if(!$CategoryList){
            return response()->json(['error' => 'Category List Route Not Found'], 404);
        } 
        $getbyid = CategoryList::with('serviceProvider')
        ->with('majorCategory')
        ->with('categoryListTag.minorCategory')
        ->where('id', $id)->first();
        return $getbyid;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(CategoryListRequest $request, $id)
    {
        $service_provider_id = $request->service_provider_id;
        $major_category_id = $request->major_category_id;
        if(!CategoryList::find($id)){
            return response()->json(['error' => 'Category List Route Not Found'], 404);
        }
        if(CategoryList::where('id',$id)
        ->where('service_provider_id', $service_provider_id)
        ->where('major_category_id', $major_category_id)
        ->exists()){
            return response()->json(['message' => 'No Changes'], 200);
        }
        $update = CategoryList::where('id', $id)
        ->update([
            'service_provider_id' => $service_provider_id,
            'major_category_id' => $major_category_id
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

    public function archived(CategoryListRequest $request, $id){

        $status = $request->status; 
        $CategoryList = CategoryList::query();
        if(!$CategoryList->withTrashed()->where('id', $id)->exists()){
            return response()->json(['error' => 'Category List Route Not Found'], 404);
        } 

        if($status == false){
            if(!CategoryList::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{
                $updateStatus = $CategoryList->where('id', $id)->update(['is_active' => false]);
                $CategoryList->where('id',$id)->delete();
                return response()->json(['message' => 'Category List Successfully Deactived!'], 200);
            }
        }
        if($status == true){
            if(CategoryList::where('id',$id)->where('is_active', true)->exists()){
                return response()->json(['message' => 'No Changes'], 200);
            }
            else{              
                $restoreUser = $CategoryList->withTrashed()->where('id',$id)->restore();
                $updateStatus = $CategoryList->update(['is_active' => true]); 
                return response()->json(['message' => 'Category List Successfully Activated!'], 200);

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
        $CategoryList = CategoryList::withTrashed()
        ->with('serviceProvider')
        ->with('majorCategory')
        ->with('categoryListTag.minorCategory')
        ->where(function($query) use($status){
            $query->where('is_active', 'LIKE', "%{$status}%" );
        })
        ->where(function($query) use($search){
            $query->orWhereHas('serviceProvider', function($q) use($search){
                $q->where('service_provider_name', 'like', '%'.$search.'%');
            })
            ->orWhereHas('majorCategory', function($q) use($search){
                $q->where('major_category_name', 'like', '%'.$search.'%')
                ->where('classification', 'like', '%'.$search.'%');
            })
            ->orWhereHas('categoryListTag.minorCategory', function($q) use($search){
                $q->where('minor_category_name', 'like', '%'.$search.'%')
                ->where('urgency_level', 'like', '%'.$search.'%');
            });
        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        return $CategoryList;
    }


}
