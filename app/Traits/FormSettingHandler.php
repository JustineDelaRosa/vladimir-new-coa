<?php
namespace App\Traits;

use App\Models\SubUnit;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait FormSettingHandler
{
    use ApiResponse;

    public function formSettingIndex(Request $request, $model)
    {
        $perPage = $request->input('per_page', null);

        $transformedResults = $model::useFilters()->orderByDesc('created_at')->get()->groupBy('subunit_id')->map(function ($item) {
            return [
                'unit' => [
                    'id' => $item[0]->unit_id,
                    'unit_name' => $item[0]->unit->unit_name,
                    'unit_code' => $item[0]->unit->unit_code,
                ],
                'created_at' => $item[0]->created_at,
                'subunit' => [
                    'id' => $item[0]->subunit_id,
                    'subunit_code' => $item[0]->subUnit->sub_unit_code,
                    'subunit_name' => $item[0]->subUnit->sub_unit_name,
                ],
                'approvers' => $item->map(function ($item) {
                    return [
                        'approver_id' => $item->approver->id ?? '-',
                        'username' => $item->approver->user->username ?? '-',
                        'employee_id' => $item->approver->user->employee_id ?? '-',
                        'first_name' => $item->approver->user->firstname ?? '-',
                        'last_name' => $item->approver->user->lastname ?? '-',
                        'layer' => $item->layer ?? '-',
                    ];
                })->sortBy('layer')->values(),
            ];
        })->values();

        if ($perPage !== null) {
            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            $transformedResults = new LengthAwarePaginator(
                $transformedResults->slice($offset, $perPage)->values(),
                $transformedResults->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return $transformedResults;
    }

    public function formSettingStore(Request $request, $model): JsonResponse
    {
        $subunitId = $request->subunit_id;
        $approverId = $request->approver_id;

        foreach ($approverId as $key => $approverIds) {
            $layer = $model::where('subunit_id', $subunitId)->max('layer');
            $model::create([
                'unit_id' => SubUnit::where('id', $subunitId)->first()->unit->id,
                'subunit_id' => $subunitId,
                'approver_id' => $approverIds,
                'layer' => $layer + 1,
            ]);
        }
        return $this->responseCreated('Created successfully');
    }

    public function formSettingArrangeLayer(Request $request, $model, $id)
    {
        $subunitId = $id;
        $approverId = $request->approver_id;
        $unitId = SubUnit::where('id', $subunitId)->first()->unit->id;
        $layer = 1;

        $approverIds = $model::where('unit_id', $unitId)
            ->where('subunit_id', $subunitId)
            ->pluck('approver_id')->toArray();

        $deletableApproverIds = array_diff($approverIds, $approverId);
        if (count($deletableApproverIds) > 0) {
            $model::where('unit_id', $unitId)
                ->where('subunit_id', $subunitId)
                ->whereIn('approver_id', $deletableApproverIds)->delete();
        }

        foreach ($approverId as $approver) {
            $model::updateOrCreate(
                [
                    'unit_id' => $unitId,
                    'subunit_id' => $subunitId,
                    'approver_id' => $approver,
                ],
                ['layer' => $layer++]
            );
        }
        return $this->responseSuccess('Unit Approvers updated Successfully');
    }

    public function formSettingDestroy($model, $subUnitId)
    {
        $record = $model::where('subunit_id', $subUnitId)->get();

        if (!$record) {
            return $this->responseSuccess('Record was already deleted');
        }

        $record->each->delete();

        return $this->responseSuccess('Deleted Successfully');
    }
}
