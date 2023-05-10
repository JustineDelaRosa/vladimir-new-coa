<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $location = Location::get();
        return $location;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
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

                    $sync = Location::updateOrCreate(
                        [
                            'sync_id' => $sync_id,
                        ],
                        [
                            'location_code' => $code, 'location_name' => $name, 'is_active' => $is_active
                        ],
                    );
                }
            }
        }
        return response()->json(['message' => 'Successfully Synched!']);
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
}
