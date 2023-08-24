<?php

namespace App\Http\Controllers\Masterlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Capex\CapexRequest;
use App\Http\Requests\Capex\SubCapexRequest;
use App\Imports\CapexImport;
use App\Models\Capex;
use App\Models\FixedAsset;
use App\Models\SubCapex;
use DateTime;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use function PHPUnit\Framework\isEmpty;

class CapexController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $limit = $request->input('limit', null);

        $capexQuery = Capex::withTrashed()->with([
            'subCapex' => function ($query) {
                $query->withTrashed();
            },
        ])
            ->where(function ($query) use ($search) {
                $query->where('capex', 'like', "%$search%")
                    ->orWhere('project_name', 'like', "%$search%");
                $query->orWhereHas('subCapex', function ($query) use ($search) {
                    $query->where('sub_capex', 'like', "%$search%")
                        ->orWhere('sub_project', 'like', "%$search%");
                });
            });

        if ($status === "deactivated") {
            $capexQuery->onlyTrashed();
        } elseif ($status === 'active') {
            $capexQuery->whereNull('deleted_at');
        }

        $capexQuery->orderByDesc('created_at');

        if ($limit !== null) {
            $result = is_numeric($limit) ? $capexQuery->paginate($limit) : $capexQuery->paginate(PHP_INT_MAX);
        } else {
            $result = $capexQuery->get();
        }

//        $result->transform(function ($capex) {
//            $capex->sub_capex =
//                $capex->subCapex
//                    ->map(function ($sub_capex) {
//                        $subCapexParts = explode('-', $sub_capex->sub_capex);
//                        if (count($subCapexParts) > 1) {
//                            $sub_capex->sub_capex = end($subCapexParts);
//                        }
//                        return $sub_capex;
//                    });
//            $capex->sub_capex_count = $capex->subCapex->count();
//            return $capex;
//        });

        $result->transform(function ($capex) {
            $capex->sub_capex = $capex->subCapex->map(function ($sub_capex) {
                $subCapexParts = explode('-', $sub_capex->sub_capex);
                if (count($subCapexParts) > 1) {
                    $sub_capex->sub_capex = end($subCapexParts);
                }
                return $sub_capex;
            });

            $capex->sub_capex_count = $capex->subCapex->filter(function ($sub_capex) {
                return $sub_capex->is_active == 1;
            })->count();

            return $capex;
        });

        return response()->json([
            'message' => 'Successfully retrieved capex.',
            'data' => $result
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CapexRequest $request)
    {
        $capex = $request->capex;
        $project_name = ucwords(strtolower($request->project_name));
        $capex = Capex::create([
            'capex' => $capex,
            'project_name' => $project_name,
            'is_active' => true
        ]);
        return response()->json([
            'message' => 'Successfully created capex.',
            'data' => $capex
        ], 201);

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $capex = Capex::query();
        if (!$capex->where('id', $id)->exists()) {
            return response()->json([
                'error' => 'Capex Route Not Found.'
            ], 404);
        }

        $capex = $capex->where('id', $id)->first();
        return response()->json([
            'message' => 'Successfully retrieved capex.',
            'data' => $capex
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CapexRequest $request, $id)
    {
        $project_name = ucwords(strtolower($request->project_name));
        $capex = Capex::find($id);

        if (!$capex) {
            return response()->json([
                'error' => 'Capex Route Not Found.'
            ], 404);
        }

        if ($capex->project_name === $project_name) {
            return response()->json([
                'message' => 'No changes.',
            ], 200);
        }

        $capex->update(['project_name' => $project_name]);

        return response()->json([
            'message' => 'Successfully updated capex.',
        ], 200);

    }

    public function archived(CapexRequest $request, $id)
    {
        $status = $request->status;

        $capex = Capex::query();
        if (!$capex->withTrashed()->where('id', $id)->exists()) {
            return response()->json([
                'error' => 'Capex Route Not Found.'
            ], 404);
        }

        if ($status == false) {
            if (!Capex::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            } else {
//                $checkFixedAsset = FixedAsset::where('capex_id', $id)->exists();
//                if ($checkFixedAsset) {
//                    return response()->json(['error' => 'Unable to archive, Capex is still in use!'], 422);
//                }
                if (Capex::where('id', $id)->exists()) {

                    $sub_capex_check = SubCapex::where('capex_id', $id)->get('id');
                    //check if any of the sub capex is in use by fixed asset
                    $checkFixedAsset = FixedAsset::whereIn('sub_capex_id', $sub_capex_check)->exists();
                    if ($checkFixedAsset) {
                        return response()->json(['error' => 'Still in use!'], 422);
                    }

                    $updateCapex = Capex::Where('id', $id)->update([
                        'is_active' => false,
                    ]);
                    //change also the status of sub capex
                    $updateSubCapex = SubCapex::whereIn('id', $sub_capex_check)->update([
                        'is_active' => false,
                    ]);
                    $archiveCapex = Capex::where('id', $id)->delete();
                    $archiveSubCapex = SubCapex::whereIn('id', $sub_capex_check)->delete();
                    return response()->json([
                        'message' => 'Successfully archived capex.',
                    ], 200);
                }

            }
        }

        if ($status == true) {
            if (Capex::where('id', $id)->where('is_active', true)->exists()) {
                return response()->json([
                    'message' => 'No Changes.'
                ], 200);
            } else {
                $restoreCapex = Capex::withTrashed()->where('id', $id)->restore();
                $updateStatus = Capex::where('id', $id)->update([
                    'is_active' => true,
                ]);
                return response()->json([
                    'message' => 'Successfully restored capex.',
                ], 200);
            }
        }
    }

    public function capexImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlx,xls,pdf,xlsx'
        ]);

        $file = $request->file('file');

        Excel::import(new CapexImport, $file);

        $data = Excel::toArray(new CapexImport, $file);
        return response()->json([
            'message' => 'Successfully imported capex.',
            'data' => $data
        ], 200);
    }

    public function sampleCapexDownload()
    {
        //download file from storage/sample
        $path = storage_path('app/sample/capex.xlsx');
        return response()->download($path);
    }

    public function capexExport(Request $request)
    {
        $search = $request->search;
        $status = $request->status;
        $startDate = $request->startDate;
        $endDate = $request->endDate;

        $capex = Capex::withTrashed()->with([
                'subCapex' => function ($query) {
                    $query->withTrashed();
                },
            ]
        )
            ->where(function ($query) use ($search) {
                $query
                    ->where("capex", "like", "%" . $search . "%")
                    ->orWhere("project_name", "like", "%" . $search . "%");
                $query->orWhereHas('subCapex', function ($query) use ($search) {
                    $query->where('sub_capex', 'like', '%' . $search . '%')
                        ->orWhere('sub_project', 'like', '%' . $search . '%');
                });
            })
            ->when($status === "deactivated", function ($query) {
                $query->onlyTrashed();
            })
            ->when($request->status === 'active', function ($query) {
                return $query->whereNull('deleted_at');
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->orderByDesc('created_at')
            ->get();

        //refactor capex response
        return $capex->map(function ($capex) {
            return [
                'id' => $capex->id,
                'capex' => $capex->capex,
                'project_name' => $capex->project_name,
                'status' => $capex->is_active ? 'Active' : 'Deactivated',
                'sub_capex' => $capex->subCapex->isEmpty() ? 'null' : $capex->subCapex->map(function ($sub_capex) {
                    return [
                        'sub_capex_id' => $sub_capex->id,
                        'sub_capex' => $sub_capex->sub_capex,
                        'sub_project' => $sub_capex->sub_project,
                        'status' => $sub_capex->is_active ? 'Active' : 'Inactive',
                    ];
                }),
            ];
        });
    }

}
