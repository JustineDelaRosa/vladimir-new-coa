<?php

namespace App\Http\Controllers;

use App\Http\Resources\Location\LocationResource;
use App\Models\Department;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $location = Location::with('locationDepartment')->get();
//        return $location;
        return response()->json([
            'message' => 'Fixed Assets retrieved successfully.',
            'data' => $location
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

//    public function store(Request $request)
//    {
//        $sync_all = $request->all();
//
//
//        foreach ($sync_all as $location) {
//            $sync_id = $location["sync_id"];
//            $code = $location["code"];
//            $name = $location["name"];
//            $is_active = $location["is_active"];
//
//            $locations = Location::updateOrCreate(
//                [
//                    "sync_id" => $sync_id,
//                ],
//                [
//                    "sync_id" => $sync_id,
//                    "location_code" => $code,
//                    "location_name" => $name,
//                    "is_active" => $is_active,
//                ]
//            );
//
//            $locations->departments()->sync($location["departments"]);
//        }
//
//        return response()->json(['message' => 'Successfully Synced!']);
//    }


    public function store(Request $request)
    {
        $location_request = $request->all('result.locations');
        if (empty($request->all())) {
            return response()->json(['message' => 'Data not Ready']);
        }

        foreach ($location_request as $locations) {
            foreach ($locations as $location) {
                foreach ($location as $loc) {
                    $sync_id = $loc['id'];
                    $code = $loc['code'];
//                    $name = strtoupper($loc['name']);
                    $name = $loc['name'];
                    $is_active = $loc['status'];
                    //one location has many departments
                    $departments = [];
                    foreach ($loc['departments'] as $department) {
                        $departments[] = $department['id'];
                    }

                    $sync = Location::updateOrCreate(
                        [
                            'sync_id' => $sync_id,
                        ],
                        [
                            'location_code' => $code,
                            'location_name' => $name,
                            'is_active' => $is_active
                        ],
                    );

                    //if sync is successful, insert this location_sync_id to department table as a foreign key
                    if ($sync) {
                        Department::WhereIn('sync_id', $departments)->update(['location_sync_id' => $sync_id]);
                    }

                }
            }
        }
        return response()->json(['message' => 'Successfully Synced!']);
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
        $Location = Location::where(function ($query) use ($status) {
            $query->where('is_active', $status);
        })
            ->where(function ($query) use ($search) {
                $query->where('location_code', 'LIKE', "%{$search}%")
                    ->orWhere('location_name', 'LIKE', "%{$search}%");
            })
            ->orderby('created_at', 'DESC')
            ->paginate($limit);
        return $Location;
    }

//    public function archived(Request $request, $id)
//    {
//        $status = $request->status;
//        $location = Location::query();
//        if (!$location->where('id', $id)->exists()) {
//            return response()->json(['error' => 'Location Route Not Found'], 404);
//        }
//
//
//        if ($status == false) {
//            if (!Location::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
//            } else {
//                $updateStatus = $location->where('id', $id)->update(['is_active' => false]);
////                $location->where('id', $id)->delete();
//                return response()->json(['message' => 'Successfully Deactived!'], 200);
//            }
//        }
//        if ($status == true) {
//            if (Location::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
//            } else {
//                //$restoreUser = $location->withTrashed()->where('id', $id)->restore();
//                $updateStatus = $location->update(['is_active' => true]);
//                return response()->json(['message' => 'Successfully Activated!'], 200);
//            }
//        }
//    }

}
