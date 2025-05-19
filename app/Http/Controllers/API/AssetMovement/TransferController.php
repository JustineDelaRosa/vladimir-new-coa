<?php

namespace App\Http\Controllers\API\AssetMovement;

use App\Http\Controllers\AssetMovementBaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetTransfer\CreateAssetTransferRequestRequest;
use App\Http\Requests\AssetTransfer\UpdateAssetTransferRequestRequest;
use App\Http\Resources\Capex\CapexResource;
use App\Models\AssetTransferApprover;
use App\Models\Disposal;
use App\Models\FixedAsset;
use App\Models\MovementNumber;
use App\Models\PullOut;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AssetTransferServices;
use App\Services\MovementApprovalServices;
use App\Traits\AssetMovement\TransferHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends AssetMovementBaseController
{
    use TransferHandler;

    public function __construct(AssetTransferServices $assetTransferServices, MovementApprovalServices $movementApprovalServices)
    {
        parent::__construct(new Transfer(), $assetTransferServices, $movementApprovalServices);
    }

    //    public function store(){
    //        return AssetTransferApprover::where('subunit_id', 49)->get();
    //    }

    protected function movementCreateFormRequest()
    {
        return CreateAssetTransferRequestRequest::class;
    }

    protected function movementUpdateFormRequest()
    {
        return UpdateAssetTransferRequestRequest::class;
    }

    public function singleViewing($transferId)
    {
        $transfer = Transfer::find($transferId);
        $movementId = $transfer->movement_id;
        $fixedAssetId = $transfer->fixed_asset_id;
        if (!$transfer) {
            return $this->responseNotFound('No Data Found');
        }
        $fixedAsset = FixedAsset::where('id', $fixedAssetId)->first();
        $movementNumber = MovementNumber::find($movementId);
        if (!$fixedAsset || !$movementNumber || !$transfer) {
            return $this->responseNotFound('No Data Found');
        }

        return $this->transformSingleFixedAssetShowData($fixedAsset, $movementNumber, $transfer);
    }

    public function editDepreciationDebit(Request $request, $movementId)
    {
        $depreciationDebitId = $request->input('depreciation_debit_id');

        DB::beginTransaction();

        try {
            $movement = MovementNumber::find($movementId);
            if (!$movement) {
                DB::rollBack();
                return $this->responseNotFound('No Data Found');
            }

            $transfer = Transfer::where('movement_id', $movementId)->get();
            if ($transfer->isEmpty()) {
                DB::rollBack();
                return $this->responseNotFound('No Data Found');
            }

            foreach ($transfer as $item) {
                $item->depreciation_debit_id = $depreciationDebitId;
                $item->save();
            }

            DB::commit();
            return $this->responseSuccess('Depreciation Debit Updated');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('Something went wrong');
        }
    }

    public function rejectItem(Request $request)
    {
        $request->validate(
            [
                'reason' => 'required|string|max:255',
            ],
            [
                'reason.required' => 'Reason is required',
                'reason.string' => 'Reason must be a string',
                'reason.max' => 'Reason must not exceed 255 characters',
            ]
        );

        DB::beginTransaction();

        try {
            $transferId = $request->input('transfer_id');

            foreach ($transferId as $id) {
                $transfer = Transfer::find($id);
                if (!$transfer) {
                    DB::rollBack();
                    return $this->responseNotFound('No Data Found');
                }

                $movementId = $transfer->movement_id;
                $userId = auth('sanctum')->user()->id;
                $reason = $request->input('reason');

                if ($transfer->receiver_id != $userId) {
                    DB::rollBack();
                    return $this->responseNotFound('You are not the receiver of this transfer');
                }

                $transfer->delete();

                $this->movementLogs($movementId, 'Rejected', $reason);
            }

            DB::commit();
            return $this->responseSuccess('Transfer Rejected');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('Something went wrong', $e);
        }
    }
}
