<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CoordinatorHandleRequest\CreateCoordinatorHandleRequest;
use App\Http\Requests\CoordinatorHandleRequest\UpdateCoordinatorHandleRequest;
use App\Models\CoordinatorHandle;
use App\Traits\CoordinatorHandleHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinatorHandleController extends Controller
{
    use ApiResponse, CoordinatorHandleHandler;


    public function index(Request $request)
    {
        $user_id = $request->input('user_id');
        $perPage = $request->input('per_page', null);

        $query = CoordinatorHandle::when($user_id, function ($query) use ($user_id) {
            $query->where('user_id', $user_id);
        });

        if ($perPage) {
            $coordinatorHandle = $query->paginate($perPage);
            $collection = $coordinatorHandle->getCollection();
        } else {
            $collection = $query->get();
        }

        $groupedHandles = $collection->groupBy('user_id')->map(function ($handles) {
            return $this->indexData($handles);
        })->values();

        if ($perPage) {
            $coordinatorHandle->setCollection($groupedHandles);
            return $coordinatorHandle;
        } else {
            return $groupedHandles;
        }
    }

    public function store(CreateCoordinatorHandleRequest $request)
    {

        $handles = $request->input('handles');
        $user_id = $request->input('user_id');
        foreach ($handles as $handle) {
            $handle['user_id'] = $user_id;
            CoordinatorHandle::create($handle);
        }

        return $this->responseSuccess('Coordinator handle created successfully');
    }

    public function update(UpdateCoordinatorHandleRequest $request, $id)
    {
        $handles = $request->input('handles');
        $user_id = $request->input('user_id');
        $coordinatorHandle = CoordinatorHandle::where('user_id', $id)->get();
        $coordinatorHandle->each->forceDelete();
        foreach ($handles as $handle) {
            $handle['user_id'] = $id;
            CoordinatorHandle::create($handle);
        }

        return $this->responseSuccess('Coordinator handle updated successfully');
    }

    public function archived(Request $request, $id)
    {
        $status = $request->status;
        $coordinatorHandle = CoordinatorHandle::query();
        if (!$coordinatorHandle->withTrashed()->where('user_id', $id)->exists()) {
            return $this->responseUnprocessable('Coordinator Handle not found.');
        }

        if ($status == false) {
            if (!CoordinatorHandle::withTrashed()->where('user_id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No Changes.');
            } else {
                if (CoordinatorHandle::withTrashed()->where('user_id', $id)->exists()) {
                    CoordinatorHandle::withTrashed()->Where('user_id', $id)->update([
                        'is_active' => false,
                    ]);
                    CoordinatorHandle::withTrashed()->where('user_id', $id)->delete();
                    return $this->responseSuccess('Successfully archived Coordinator Handle.');
                }

            }
        }
        if ($status == true) {
            if (CoordinatorHandle::withTrashed()->where('user_id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No Changes.');
            } else {
                if (CoordinatorHandle::withTrashed()->where('user_id', $id)->exists()) {
                    CoordinatorHandle::withTrashed()->restore();
                    CoordinatorHandle::Where('user_id', $id)->update([
                        'is_active' => true,
                    ]);
                    return $this->responseSuccess('Successfully restored Coordinator Handle.');
                }

            }
        }
    }
}
