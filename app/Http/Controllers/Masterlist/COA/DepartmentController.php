<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\LocationDepartment;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use function PHPUnit\Framework\isEmpty;

class DepartmentController extends Controller
{

    use APIResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Http\Response
     */
    public function index(Request $request)
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

        $departmentStatus = $request->status ?? 'active';
        $isActiveStatus = ($departmentStatus === 'deactivated') ? 0 : 1;
        $department = Department::where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'DESC')
            ->useFilters()
            ->dynamicPaginate();

        $department->transform(function ($department) {
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
                        'location_status' => $locations->is_active ?? '-',
                    ];
                }),
                'division' => [
                    'division_id' => $department->division->id ?? "-",
                    'division_name' => $department->division->division_name ?? "-",
                ],
//                'subunit' => $department->subUnit->isEmpty() ? [] : $department->subUnit->map(function ($subunit) {
//                    return [
//                        'subunit_id' => $subunit->id ?? "-",
//                        'subunit_sync_id' => $subunit->sync_id ?? "-",
//                        'subunit_code' => $subunit->subunit_code ?? "-",
//                        'subunit_name' => $subunit->subunit_name ?? "-",
//                        'subunit_status' => $subunit->is_active ?? '-',
//                    ];
//                }),
            'subunit' => $department->subUnit->map(function ($subunit) {
                    return [
                        'subunit_id' => $subunit->id ?? "-",
                        'subunit_code' => $subunit->sub_unit_code ?? "-",
                        'subunit_name' => $subunit->sub_unit_name ?? "-",
                        'subunit_status' => $subunit->is_active ?? "-",
                    ];
                }),
                'department_code' => $department->department_code,
                'department_name' => $department->department_name,
                'is_active' => $department->is_active,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
            ];
        });

        return $department;
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
//            return response()->json(['message' => 'Company Data not Ready'], 422);
            return $this->responseUnprocessable('Company Data not Ready');
        }
        $departmentData = $request->input('result.departments');
        if (empty($request->all()) || empty($request->input('result.departments'))) {
//            return response()->json(['message' => 'Data not Ready']);
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach ($departmentData as $departments) {
            $sync_id = $departments['id'];
            $code = $departments['code'];
            $company_sync_id = $departments['company']['id'];
            $name = $departments['name'];
            $is_active = $departments['status'];

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
        }
//        return response()->json(['message' => 'Successfully Synced!']);
        return $this->responseSuccess('Successfully Synced!');
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
                        'location_status' => $locations->is_active ?? '-',
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
