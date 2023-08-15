<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\LocationDepartment;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Http\Response
     */
    public function index()
    {

//        $client = new Client();
//        $token = '9|u27KMjj3ogv0hUR8MMskyNmhDJ9Q8IwUJRg8KAZ4';
//        $response = $client->request('GET', 'http://rdfsedar.com/api/data/employees', [
//            'headers' => [
//                'Authorization' => 'Bearer ' . $token,
//                'Accept' => 'application/json',
//            ],
//        ]);
//
//// Get the body content from the response
//        $body = $response->getBody()->getContents();
//
//// Decode the JSON response into an associative array
//        $data = json_decode($body, true);
//        $nameToCheck = 'Perona, jerome';
//
//        if (!empty($data['data']) && is_array($data['data'])) {
//            foreach ($data['data'] as $employee) {
//                if (!empty($employee['general_info']) && stripos($employee['general_info']['full_name'], $nameToCheck) !== false) {
//                    echo $employee['general_info']['full_id_number'] . PHP_EOL;
//                    break;
//                }
//            }
//        }
        $results = [];
        $department = Department::with('location')->where('is_active', 1)->get();
        foreach ($department as $departments) {
            $results[] = [
                'id' => $departments->id,
                'sync_id' => $departments->sync_id,
                'company' => [
                    'company_id' => $departments->company->id ?? "-",
                    'company_sync_id' => $departments->company->sync_id ?? "-",
                    'company_code' => $departments->company->company_code ?? "-",
                    'company_name' => $departments->company->company_name ?? "-",
                ],
                'locations' => $departments->location->map(function ($locations) {
                    return [
                        'location_id' => $locations->id ?? "-",
                        'location_sync_id' => $locations->sync_id ?? "-",
                        'location_code' => $locations->location_code ?? "-",
                        'location_name' => $locations->location_name ?? "-",
                    ];
                }),
                'division' => [
                    'division_id' => $departments->division->id ?? "-",
                    'division_name' => $departments->division->division_name ?? "-",
                ],
                'department_code' => $departments->department_code,
                'department_name' => $departments->department_name,
                'is_active' => $departments->is_active,
                'created_at' => $departments->created_at,
                'updated_at' => $departments->updated_at,
            ];
        }
        return $results;
    }

    /**
     * Stores the department data*/

//    public function store(Request $request)
//    {
//        $sync = $request->all();
//
//        $department = Department::upsert($sync, ["sync_id"], ["department_code", "department_name","company_id" , "is_active"]);
//
//        return response()->json(['message' => 'Successfully Synced!']);
//    }

    public function store(Request $request)
    {
        $company = Company::all()->isEmpty();
        if ($company) {
            return response()->json(['message' => 'Company Data not Ready'], 422);
        }

        $department_request = $request->all('result.departments');
        if (empty($request->all())) {
            return response()->json(['message' => 'Data not Ready']);
        }

        foreach ($department_request as $departments) {
            foreach ($departments as $department) {
                foreach ($department as $dept) {

                    $sync_id = $dept['id'];
                    $code = $dept['code'];
//                    $name = strtoupper($dept['name']);
                    $company_sync_id = $dept['company']['id'];
                    $name = $dept['name'];
                    $is_active = $dept['status'];

                    $sync = Department::updateOrCreate(
                        [
                            'sync_id' => $sync_id,
                        ],
                        [
                            'department_code' => $code,
                            'department_name' => $name,
                            'company_sync_id' => $company_sync_id,
                            'is_active' => $is_active
                        ],
                    );

//                    $department = Department::where('sync_id', $sync_id)->first();
//                    if ($department) {
//                        if ($department->is_active == 0) {
//                            $is_active = 0;
//                        }
//                    }
                }
            }
        }
        return response()->json(['message' => 'Successfully Synced!']);
    }

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

        //if the division_id column is null, it will not be included in the search query to avoid error
        $Department = Department::where(function ($query) use ($status) {
            $query->where('is_active', $status);
        })
            ->where(function ($query) use ($search) {
                $query->where('department_code', 'LIKE', "%{$search}%")
                    ->orWhere('department_name', 'LIKE', "%{$search}%");
                $query->orWhereHas('company', function ($query) use ($search) {
                    $query->where('company_name', 'LIKE', "%{$search}%")
                        ->orWhere('company_code', 'LIKE', "%{$search}%");
                });
                $query->orWhereHas('location', function ($query) use ($search) {
                    $query->where('location_name', 'LIKE', "%{$search}%")
                        ->orWhere('location_code', 'LIKE', "%{$search}%");
                });
                $query->orWhereHas('division', function ($query) use ($search) {
                    $query->where('division_name', 'LIKE', "%{$search}%");
                });
            })
            ->orderby('created_at', 'DESC')
            ->paginate($limit);

        $Department->getCollection()->transform(function ($department) {
            return [
                'id' => $department->id,
                'sync_id' => $department->sync_id,
                'company' => [
                    'company_id' => $department->company->id ?? "-",
                    'company_sync_id' => $department->company->sync_id ?? "-",
                    'company_code' => $department->company->company_code ?? "-",
                    'company_name' => $department->company->company_name ?? "-",
                ],
                'locations' => $department->location->map(function ($locations) {
                    return [
                        'location_id' => $locations->id ?? "-",
                        'location_sync_id' => $locations->sync_id ?? "-",
                        'location_code' => $locations->location_code ?? "-",
                        'location_name' => $locations->location_name ?? "-",
                    ];
                }),
                'division' => [
                    'division_id' => $department->division->id ?? "-",
                    'division_name' => $department->division->division_name ?? "-",
                ],
                'department_code' => $department->department_code,
                'department_name' => $department->department_name,
                'is_active' => $department->is_active,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
            ];
        });
        return $Department;
    }



//    public function archived(Request $request, $id)
//    {
//        $status = $request->status;
//        $department = Department::query();
//        if (!$department->where('id', $id)->exists()) {
//            return response()->json(['error' => 'Department Route Not Found'], 404);
//        }
//
//
//        if ($status == false) {
//            if (!Department::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
//            } else {
//                $department = Department::find($id);
//                if ($department && $department->locations->isNotEmpty()) {
//                    // The department is in the pivot table
//                    return response()->json(['message' => 'Cannot Deactivate Department. Department is in use.'], 200);
//                }
//
//                $updateStatus = $department->where('id', $id)->update(['is_active' => false]);
////                $department->where('id', $id)->delete();
//                return response()->json(['message' => 'Successfully Deactived!'], 200);
//            }
//        }
//        if ($status == true) {
//            if (Department::where('id', $id)->where('is_active', true)->exists()) {
//                return response()->json(['message' => 'No Changes'], 200);
//            } else {
//                //check if company is active
//                $department = Department::where('id', $id)->first();
//                $company = $department->company()->first();
//                if ($company->is_active == false) {
//                    return response()->json(['message' => 'Cannot Activate Department. Company is not active.'], 200);
//                }
////              $restoreUser = $department->withTrashed()->where('id', $id)->restore();
//                $updateStatus = $department->update(['is_active' => true]);
//                return response()->json(['message' => 'Successfully Activated!'], 200);
//            }
//        }
//    }
}
