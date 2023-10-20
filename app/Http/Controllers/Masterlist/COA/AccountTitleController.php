<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
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
        $accountTitle = AccountTitle::where('is_active', 1)->get();
        return $accountTitle;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $accountTitleData = $request->input('result.account_titles');
        if (empty($request->all()) || empty($request->input('result.account_titles'))) {
            return response()->json(['message' => 'Data not Ready']);
        }

        foreach ($accountTitleData as $accountTitles) {
            $sync_id = $accountTitles['id'];
            $code = $accountTitles['code'];
            $name = $accountTitles['name'];
            $is_active = $accountTitles['status'];

            $accountTitle = AccountTitle::where('sync_id', $sync_id)->first();
            if ($accountTitle) {
                if ($accountTitle->is_active == 0) {
                    $is_active = 0;
                }
            }

            $sync = AccountTitle::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'account_title_code' => $code,
                    'account_title_name' => $name,
                    'is_active' => $is_active
                ],
            );
        }
        return response()->json(['message' => 'Successfully Synched!']);
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
        $AccountTitle = AccountTitle::where(function ($query) use ($status) {
            $query->where('is_active', $status);
        })
            ->where(function ($query) use ($search) {
                $query->where('account_title_code', 'LIKE', "%{$search}%")
                    ->orWhere('account_title_name', 'LIKE', "%{$search}%");
            })
            ->orderby('created_at', 'DESC')
            ->paginate($limit);
        return $AccountTitle;
    }

    public function archived(Request $request, $id)
    {
        $status = $request->status;
        $accountTitle = AccountTitle::query();
        if (!$accountTitle->where('id', $id)->exists()) {
            return response()->json(['error' => 'Account Title Route Not Found'], 404);
        }


        if ($status == false) {
            if (!AccountTitle::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
                $updateStatus = $accountTitle->where('id', $id)->update(['is_active' => false]);
//                $accountTitle->where('id', $id)->delete();
                return response()->json(['message' => 'Successfully Deactivated!'], 200);
            }
        }
        if ($status == true) {
            if (AccountTitle::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json(['message' => 'No Changes'], 200);
            } else {
//              $restoreUser = $accountTitle->withTrashd()-e>where('id', $id)->restore();
                $updateStatus = $accountTitle->update(['is_active' => true]);
                return response()->json(['message' => 'Successfully Activated!'], 200);
            }
        }
    }
}
