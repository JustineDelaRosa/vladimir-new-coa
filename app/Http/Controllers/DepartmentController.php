<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $department = Department::get();
        return $department;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $department_request = $request->all('result.departments');
        if (empty($request->all())) {
            return response()->json(['message' => 'Data not Ready']);
        }

        foreach ($department_request as $departments) {
            foreach ($departments as $department) {
                foreach ($department as $dept) {
                    $sync_id = $dept['id'];
                    $code = $dept['code'];
                    $name = $dept['name'];
                    $is_active = $dept['status'];

                    $sync = Department::updateOrCreate(
                        [
                            'sync_id' => $sync_id,
                        ],
                        [
                            'department_code' => $code, 'department_name' => $name, 'is_active' => $is_active
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
        $Department = Department::where(function ($query) use ($status) {
            $query->where('is_active', $status);
        })
            ->where(function ($query) use ($search) {
                $query->where('department_code', 'LIKE', "%{$search}%")
                    ->orWhere('department_name', 'LIKE', "%{$search}%");
            })
            ->orderby('created_at', 'DESC')
            ->paginate($limit);
        return $Department;
    }
}
