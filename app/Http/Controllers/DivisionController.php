<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;
use App\Http\Requests\Division\DivisionRequest;
use App\Models\MajorCategory;

class DivisionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //user division model then get major category and minor catery using major category
        $division = Division::with('major_category')->get();
        return response()->json([
            'data' => $division
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(DivisionRequest $request)
    {
        $division_name = ucwords(strtolower($request->division_name));

        Division::create([
            'division_name' => $division_name,
            'is_active' => 1
        ]);
        return response()->json([
            'message' => 'Successfully Created!',
            'data' => Division::where('division_name', $division_name)->first()
        ], 201);
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
    public function update(DivisionRequest $request, $id)
    {
        $division_name = ucwords(strtolower($request->division_name));

        if (!Division::where('id', $id)->exists()) {
            return response()->json(['error' => 'Division Route Not Found'], 404);
        }
        if (Division::where('id', $id)->where([
            'division_name' => $division_name,
        ])->exists()) {
            return response()->json(['message' => 'No Changes'], 200);
        }
        $update = Division::where('id', $id)->update([
            'division_name' => $division_name,
        ]);
        return response()->json(['message' => 'Successfully Updated!', 'data' => Division::where('id', $id)->first()], 200);
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

        //check id Division is tag or not
        $division_tag_check = MajorCategory::where('division_id', $id)->exists();
        if ($division_tag_check) {
            return response()->json(['message' => 'Unable to Archived!, Division was tagged!'], 409);
        }

        if ($status == false) {
            if (!Division::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
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
