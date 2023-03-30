<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use Illuminate\Http\Request;

class AccountTitleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $accountTitle = AccountTitle::get();
        return $accountTitle;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $accountitle_request = $request->all('result.account_titles');
        if(empty($request->all())){
            return response()->json(['message' => 'Data not Ready']);
        }
        
        foreach($accountitle_request as $accountitles){
            foreach($accountitles as $accountitle){
                foreach($accountitle as $accountTitle){
                    $code = $accountTitle['code'];
                    $name = $accountTitle['name'];
                    $is_active = $accountTitle['status'];

                    $sync = AccountTitle::updateOrCreate([
                        'account_title_code' => $code],
                        ['account_title_name' => $name, 'is_active' => $is_active],
                    );
                    // $sync = Company::upsert([
                    //     ['company_code' => $code, 'company_name' => $name,  'is_active' => $is_active]
                    //     ], ['company_code'], ['is_active']);
                    
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

    public function search(Request $request){
        $search = $request->query('search');
        $limit = $request->query('limit');
        $page = $request->get('page');
        $status = $request->query('status');
        if($status == NULL ){
            $status = 1;
        }
        if($status == "active"){
            $status = 1;
        }
        if($status == "deactivated"){
            $status = 0;
        }
        if($status != "active" || $status != "deactivated"){
            $status = 1;
        }
        $AccountTitle = AccountTitle::where(function($query) use($status){
            $query->where('is_active', $status);
        })
        ->where(function($query) use($search){
            $query->where('account_title_code', 'LIKE', "%{$search}%" )
            ->orWhere('account_title_name', 'LIKE', "%{$search}%" );
     
        })
        ->orderby('created_at', 'DESC')
        ->paginate($limit);
        return $AccountTitle;
    }
}
