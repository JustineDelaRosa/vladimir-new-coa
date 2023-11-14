<?php

namespace App\Http\Controllers\API\AssetApprovalLogger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AssetApprovalLoggerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // return Activity::all();

        $activityLog = Activity::useFilters()->dynamicPaginate();

        $activityLog->transform(function ($item) {
            $transactionNumber = $item->assetApproval->transaction_number;
            return[
                'id' => $item->id,
                'log_name' => $item->log_name,
                'description' => $item->description,
                'subject_id' => $item->subject_id,
                'subject_type' => $item->subject_type,
                'causer_id' => [
                    'id' => $item->causer->id,
                    'username' => $item->causer->username,
                    'employee_id' => $item->causer->employee_id,
                    'firstname' => $item->causer->firstname,
                    'lastname' => $item->causer->lastname,
                ],
                'properties' => $item->properties,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return $activityLog;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
}
