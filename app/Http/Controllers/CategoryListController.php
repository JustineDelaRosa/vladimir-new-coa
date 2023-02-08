<?php

namespace App\Http\Controllers;

use App\Models\CategoryList;
use Illuminate\Http\Request;

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
        ->with('minorCategory')
        ->get();
        return $CategoryList;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $service_provider_id = $request->service_provider_id;
        $major_category_id = $request->major_category_id;
        $minor_category_id = $request->minor_category_id;

        $create = CategoryList::create([
            'service_provider_id' => $service_provider_id,
            'major_category_id' => $major_category_id,
            'minor_category_id' => $minor_category_id,
            'is_active' => 1
        ]);
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
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
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
}
