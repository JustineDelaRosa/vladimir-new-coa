<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Location;
use App\Models\SubUnit;
use App\Traits\COA\LocationHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{

    use ApiResponse, locationHandler;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        //        $location = Location::with('departments')->where('is_active', 1)->get();
        ////        return $location;
        //        return response()->json([
        //            'message' => 'Fixed Assets retrieved successfully.',
        //            'data' => $location
        //        ], 200);

        $locationStatus = $request->status ?? 'active';
        $isActiveStatus = ($locationStatus === 'deactivated') ? 0 : 1;
//        with('receivingWarehouse')->
        $location = Location::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();


        return $this->transformLocation($location);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function store(Request $request)
    {
        if (SubUnit::all()->isEmpty()) {
            //            return response()->json(['message' => 'Department data not ready'], 422);
            return $this->responseUnprocessable('Department data not ready');
        }

        $locationData = $request->input('result');
        if (empty($request->all()) || empty($request->input('result'))) {
            //            return response()->json(['message' => 'Data not Ready']);
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach ($locationData as $location) {
            $locationInDB = Location::updateOrCreate(
                ['sync_id' => $location['id']],
                [
                    'location_code' => $location['code'],
                    'location_name' => $location['name'],
                    'is_active' => $location['deleted_at'] == null ? 1 : 0
                ]
            );

            $department_ids = array_column($location['sub_units'], 'id');
            foreach ($department_ids as $id) {
                if (!SubUnit::where('sync_id', $id)->exists()) {
                    return $this->responseUnprocessable('Some of subunit is missing sync the subunit first!');
                }
            }
            $locationInDB->subunit()->sync($department_ids);

            //            //if the status is false, detach the department in the pivot table
            //            if ($location['status'] == false) {
            //                $locationInDB->departments()->detach();
            //            }
        }

        //        return response()->json(['message' => 'Successfully Synced!']);
        return $this->responseSuccess('Successfully Synced!');
    }

    //    public function store(Request $request)
    //    {
    //
    //        $departmentsExist = Department::all()->isEmpty();
    //        if ($departmentsExist) {
    //            return response()->json(['message' => 'Sync the department first!'], 422);
    //        }
    //
    //        $location_request = $request->all('result.locations');
    //        if (empty($request->all())) {
    //            return response()->json(['message' => 'Data not Ready']);
    //        }
    //
    //        //check if the department table is not empty
    ////        if (Department::all()->isEmpty()) {
    ////            return response()->json(['message' => 'Sync the department first!'], 422);
    ////        }
    //
    //        foreach ($location_request as $locations) {
    //            foreach ($locations as $location) {
    //                foreach ($location as $loc) {
    //                    $sync_id = $loc['id'];
    //                    $code = $loc['code'];
    ////                    $name = strtoupper($loc['name']);
    //                    $name = $loc['name'];
    //                    $is_active = $loc['status'];
    //                    //one location has many departments
    ////                    $departments = [];
    ////                    foreach ($loc['departments'] as $department) {
    ////                        $departments[] = $department['id'];
    ////                    }
    //
    //                    $sync = Location::updateOrCreate(
    //                        [
    //                            'sync_id' => $sync_id,
    //                        ],
    //                        [
    //                            'location_code' => $code,
    //                            'location_name' => $name,
    //                            'is_active' => $is_active
    //                        ],
    //                    );
    //
    //                    //sync the both department and location table in the pivot table
    //                    $sync->departments()->sync($loc['departments']);
    //                }
    //            }
    //        }
    //        return response()->json(['message' => 'Successfully Synced!']);
    //    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
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

        $Location->getCollection()->transform(function ($location) {
            return [
                'id' => $location->id,
                'sync_id' => $location->sync_id,
                'location_code' => $location->location_code,
                'location_name' => $location->location_name,
                'departments' => $location->departments->map(function ($departments) {
                    return [
                        'id' => $departments->id ?? '-',
                        'sync_id' => $departments->sync_id ?? '-',
                        'department_code' => $departments->department_code ?? '-',
                        'department_name' => $departments->department_name ?? '-',
                        'department_status' => $departments->is_active ?? '-',
                    ];
                }),
                'is_active' => $location->is_active,
                'created_at' => $location->created_at,
                'updated_at' => $location->updated_at,
            ];
        });
        return $Location; //Todo: Add all the departments tagged to this location
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


    public function warehouseTagging(Request $request, $id)
    {
        $warehouseId = $request->warehouse_id;
        $location = Location::find($id);
        if (!$location) {
            return $this->responseNotFound('Location not found');
        }
        $location->update(['warehouse_id' => $warehouseId]);
        return $this->responseSuccess('Warehouse Tagged Successfully');
    }

}
