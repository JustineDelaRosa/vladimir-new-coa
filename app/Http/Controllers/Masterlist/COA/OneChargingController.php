<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Http\Requests\OneCharging\SyncOneChargingRequest;
use App\Imports\OneChargingUpdateImport;
use App\Models\AssetRequest;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\OneCharging;
use App\Models\SubUnit;
use App\Models\Unit;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;

class OneChargingController extends Controller
{
    use APIResponse;

    public function index(Request $request)
    {
        $oneChargingStatus = $request->status ?? 'active';
        $userId = $request->user_id;
        $isActiveStatus = ($oneChargingStatus === 'deactivated') ? 0 : 1;
        return OneCharging::with('receivingWarehouse')->withTrashed()
            ->when($userId, function ($query) use ($userId) {
                $query->wherehas('coordinatorHandle', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                });
            })
            ->when($isActiveStatus === 1, function ($query) {
                return $query->whereNull('deleted_at');
            }, function ($query) {
                return $query->whereNotNull('deleted_at');
            })->useFilters()->dynamicPaginate();
    }


    public function getOneChargingAPI()
    {

        // Just the URL
        $url = config('app.one_charging.sync_url');

        // Just the API key
        $key = config('app.one_charging.sync_key');


        $response = Http::withHeaders(['API_KEY' => $key])
            ->get($url);

        $oneChargingData = $response->json();
//        return $oneChargingData['data'];

        foreach ($oneChargingData['data'] as $oneCharging) {
            try {

                $sync_id = $oneCharging['id'];
                $code = $oneCharging['code'];
                $name = $oneCharging['name'];

                // These queries are now safe because validation ensures they exist
                $company_id = Company::where('company_code', $oneCharging['company_code'])->first()->id;
//                $company_id = Company::where('company_name', $oneCharging['company_name'])->first()->id ?? Company::where('company_code', $oneCharging['company_code'])->first()->id;
                $company_code = $oneCharging['company_code'];
                $company_name = $oneCharging['company_name'];

                $business_unit_id = BusinessUnit::where('business_unit_code', $oneCharging['business_unit_code'])->first()->id;
//                $business_unit_id = BusinessUnit::where('business_unit_name', $oneCharging['business_unit_name'])->first()->id ?? BusinessUnit::where('business_unit_code', $oneCharging['business_unit_code'])->first()->id;
                $business_unit_code = $oneCharging['business_unit_code'];
                $business_unit_name = $oneCharging['business_unit_name'];

                $department_id = Department::where('department_code', $oneCharging['department_code'])->first()->id;
//                $department_id = Department::where('department_name', $oneCharging['department_name'])->first()->id ?? Department::where('department_code', $oneCharging['department_code'])->first()->id;
                $department_code = $oneCharging['department_code'];
                $department_name = $oneCharging['department_name'];

                $unit_id = Unit::where('unit_code', $oneCharging['unit_code'])->first()->id;
//                $unit_id = Unit::where('unit_name', $oneCharging['unit_name'])->first()->id ?? Unit::where('unit_code', $oneCharging['unit_code'])->first()->id;
                $unit_code = $oneCharging['unit_code'];
                $unit_name = $oneCharging['unit_name'];

                $subunit_id = SubUnit::where('sub_unit_code', $oneCharging['sub_unit_code'])->first()->id;
//                $subunit_id = SubUnit::where('sub_unit_name', $oneCharging['sub_unit_name'])->first()->id ?? SubUnit::where('sub_unit_code', $oneCharging['sub_unit_code'])->first()->id;
                $subunit_code = $oneCharging['sub_unit_code'];
                $subunit_name = $oneCharging['sub_unit_name'];

//                $location_id = Location::where('location_code', $oneCharging['location_code'])->first()->id;
                $location_id = Location::where('location_name', $oneCharging['location_name'])->first()->id ?? Location::where('location_code', $oneCharging['location_code'])->first()->id;
                $location_code = $oneCharging['location_code'];
                $location_name = $oneCharging['location_name'];

                $is_deleted = !is_null($oneCharging['deleted_at']);

                // Rest of your code remains the same
                $oneChargingRecord = OneCharging::updateOrCreate(
                    [
                        'sync_id' => $sync_id,
                    ],
                    [
                        'code' => $code,
                        'name' => $name,
                        'company_id' => $company_id,
                        'company_code' => $company_code,
                        'company_name' => $company_name,
                        'business_unit_id' => $business_unit_id,
                        'business_unit_code' => $business_unit_code,
                        'business_unit_name' => $business_unit_name,
                        'department_id' => $department_id,
                        'department_code' => $department_code,
                        'department_name' => $department_name,
                        'unit_id' => $unit_id,
                        'unit_code' => $unit_code,
                        'unit_name' => $unit_name,
                        'subunit_id' => $subunit_id,
                        'subunit_code' => $subunit_code,
                        'subunit_name' => $subunit_name,
                        'location_id' => $location_id,
                        'location_code' => $location_code,
                        'location_name' => $location_name,
                    ]
                );

                // Handle soft deletion
                if ($is_deleted) {
                    if (!$oneChargingRecord->trashed()) {
                        $oneChargingRecord->delete();
                    }
                } else {
                    if ($oneChargingRecord->trashed()) {
                        $oneChargingRecord->restore();
                    }
                }
            } catch (\Exception $e) {
                // Log exception or handle specific record error
                \Log::error('Error syncing OneCharging record: ' . $e->getMessage(), [
                    'data' => $oneCharging
                ]);
//                return  $e;
                return $this->responseUnprocessable("Something when wrong while syncing data. Please contact support");
            }
        }

        return $this->responseSuccess("One Charging data synced successfully");
    }

    public function syncOnceCharging(Request $request){
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlx,xls,pdf,xlsx'
        ]);

        $file = $request->file('file');
        Excel::import(new OneChargingUpdateImport(), $file);

        //put into an array the data from the Excel file
        $data = Excel::toArray(new OneChargingUpdateImport, $file);

        return $this->responseSuccess('COA Update imported successfully.', $data);
    }

    public function updateRequestOneCharging(){
        $requestSubUnit = AssetRequest::get('subunit_id', 'reference_number', 'id');

        //match the subunit_id with the one_charging_ids sub_unit_id of the asset request
        $notInOneCharging = AssetRequest::join('sub_units', 'asset_requests.subunit_id', '=', 'sub_units.id')
            ->whereNotIn('sub_units.sub_unit_code', OneCharging::pluck('subunit_code'))
            ->get([
                'asset_requests.id',
                'asset_requests.subunit_id',
                'asset_requests.reference_number',
                'sub_units.sub_unit_code'
            ]);

        return $notInOneCharging;

    }

    public function importRequestOneCharging(){
        try {
            $assetRequests = AssetRequest::with('subUnit')
                ->whereNull('one_charging_id')
                ->get();

            $updatedCount = 0;
            $notFoundCount = 0;
            $updatedIds = [];
            $notFoundIds = [];

            foreach ($assetRequests as $request) {
                $oneCharging = OneCharging::whereNull('deleted_at')
//                    ->where('company_code', $request->company_code)
//                    ->where('business_unit_code', $request->business_unit_code)
//                    ->where('department_code', $request->department_code)
//                    ->where('unit_code', $request->unit_code)
                    ->where('subunit_code', $request->subunit_code)
                    ->where('location_code', $request->location_code)
                    ->first();

                if ($oneCharging) {
                    $request->one_charging_id = $oneCharging->id;
                    $request->save();
                    $updatedCount++;
                    $updatedIds[] = $request->id;
                } else {
                    $notFoundCount++;
                    $notFoundIds[] = $request->id;
                }
            }

            return $this->responseSuccess('OneCharging import completed', [
                'total_processed' => count($assetRequests),
                'updated' => $updatedCount,
                'updated_ids' => $updatedIds,
                'not_found' => $notFoundCount,
                'not_found_ids' => $notFoundIds
            ]);

        } catch (\Exception $e) {
            \Log::error('Error importing OneCharging data: ' . $e->getMessage());
            return $this->responseUnprocessable("An error occurred while importing OneCharging data");
        }
    }
}
