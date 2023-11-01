<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrinterIP\PrinterIPRequest;
use App\Models\PrinterIP;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class PrinterIPController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $printerIPStatus = $request->status ?? 'active';
        $isActiveStatus = ($printerIPStatus === 'deactivated') ? 0 : 1;


        $printerIP = PrinterIP::where('is_active', $isActiveStatus)
            ->orderByDesc('created_at')
            ->useFilters()
            ->dynamicPaginate();

        return $printerIP;


//        $printerIP = PrinterIP::get();
//        return response()->json([
//            'message' => 'Successfully retrieved printer ip.',
//            'data' => $printerIP
//        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PrinterIPRequest $request)
    {
        $printerIP = $request->ip;
        $name = $request->name;

        $printerIP = PrinterIP::create([
            'ip' => $printerIP,
            'name' => $name,
            'is_active' => false
        ]);
//        return response()->json([
//            'message' => 'Successfully created printer ip.',
//            'data' => $printerIP
//        ], 200);
    return $this->responseCreated('Successfully Created');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $printerIP = PrinterIP::find($id);
        if(!$printerIP){
            return response()->json([
                'message' => 'Printer ip not found.'
            ], 404);
        }
        return $printerIP;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PrinterIPRequest $request, $id)
    {
        $printerIP = PrinterIP::find($id);
        if(!$printerIP){
            return response()->json([
                'message' => 'Printer ip not found.'
            ], 404);
        }
        $printerIP->ip = $request->ip;
        $printerIP->name = $request->name;
        $printerIP->save();
//        return response()->json([
//            'message' => 'Successfully updated printer ip.',
//            'data' => $printerIP
//        ], 200);

        return $this->responseSuccess('Successfully Updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $printerIP = PrinterIP::find($id);
        if(!$printerIP){
            return response()->json([
                'message' => 'Printer ip not found.',
                'data' => null
            ], 404);
        }
        $printerIP->delete();
//        return response()->json([
//            'message' => 'Successfully deleted printer ip.',
//            'data' => $printerIP
//        ], 200);

        return $this->responseSuccess('Successfully Deleted');
    }

    public function activateIP(Request $request, $id)
    {
        $printer = PrinterIP::find($id);

        if (!$printer) {
//            return response()->json([
//                'message' => 'Printer IP not found.'
//            ], 404);
            return $this->responseNotFound('Printer IP not found.');
        }

        // Get current status
        $currentStatus = $printer->is_active;

        // if the printer is currently active, just deactivate it
        if ($currentStatus == true) {
            $printer->is_active = false;
        }

        // if the printer is currently inactive, activate it and if necessary, deactivate another active one
        else {
            // Get all active IPs
//            $activeIPs = PrinterIP::where('is_active', true)->orderBy('updated_at', 'asc')->get();
//            // If there are 2 or more active IPs not including the current
//            if ($activeIPs->count() >= 2) {
//                // Deactivate the oldest one
//                $oldestIP = $activeIPs->first();
//                $oldestIP->is_active = false;
//                $oldestIP->save();
//            }

            // Activate the printer
            $printer->is_active = true;
        }

        $printer->save();

//        return response()->json([
//            'message' => 'Successfully changed printer IP status.',
//        ], 200);
        return $this->responseSuccess('Successfully changed printer IP status.');
    }

    public function getClientIP(Request $request){
        $ip = $_SERVER['REMOTE_ADDR'];
//        $ip = request()->ip();
//        return response()->json([
//            'message' => 'Successfully retrieved client ip.',
//            'data' => $ip
//        ], 200);
        return $this->responseSuccess('Successfully retrieved client ip.', $ip);
    }


//    public function getClientIP(Request $request) {
//        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
//            $ip = $_SERVER['HTTP_CLIENT_IP'];
//        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
//            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
//        } else {
//            $ip = $_SERVER['REMOTE_ADDR'];
//        }
//
//        return response()->json([
//            'message' => 'Successfully retrieved client ip.',
//            'data' => $ip
//        ], 200);
//    }
}
