<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrinterIP\PrinterIPRequest;
use App\Models\PrinterIP;
use Illuminate\Http\Request;

class PrinterIPController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $printerIP = PrinterIP::get();
        return response()->json([
            'message' => 'Successfully retrieved printer ip.',
            'data' => $printerIP
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PrinterIPRequest $request)
    {
        $printerIP = $request->printer_ip;
        $name = $request->name;
        $printerIP = PrinterIP::create([
            'ip' => $printerIP,
            'name' => $name,
            'is_active' => false
        ]);
        return response()->json([
            'message' => 'Successfully created printer ip.',
            'data' => $printerIP
        ], 200);
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
        return response()->json([
            'message' => 'Successfully retrieved printer ip.',
            'data' => $printerIP
        ], 200);
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
        $printerIP->ip = $request->printer_ip;
        $printerIP->name = $request->name;
        $printerIP->save();
        return response()->json([
            'message' => 'Successfully updated printer ip.',
            'data' => $printerIP
        ], 200);
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
        $printerIP->delete();
        return response()->json([
            'message' => 'Successfully deleted printer ip.',
            'data' => $printerIP
        ], 200);
    }

    public function activateIP(PrinterIPRequest $request)
    {

        $printerID = $request->printer_id;
        //activate printer ip and deactivate other printer ip only one printer ip can be active
        $printer = PrinterIP::find($printerID);
        $printer->is_active = true;
        $printer->save();

        $printer = PrinterIP::where('id', '!=', $printerID)->get();
        foreach ($printer as $print) {
            $print->is_active = false;
            $print->save();
        }
        return response()->json([
            'message' => 'Successfully activated printer ip.',
            'data' => $printer
        ], 200);
    }
}
