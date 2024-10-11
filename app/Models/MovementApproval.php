<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovementApproval extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function movementNumber()
    {
        return $this->belongsTo(MovementNumber::class, 'movement_number_id', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(Approvers::class, 'approver_id', 'id');
    }

    public function transfers()
    {
        return $this->hasManyThrough(
            Transfer::class,
            MovementNumber::class,
            'id', // Foreign key on MovementNumber table...
            'movement_id', // Foreign key on Transfers table...
            'movement_number_id', // Local key on MovementApprovals table...
            'id' // Local key on MovementNumbers table...
        );
    }

    public function createMovementApproval($movementNumberId, $movementTypeModel, $subUnitId, $approverIds)
    {
        $user = auth('sanctum')->user();
        $approversId = [];
        foreach ($approverIds as $approver) {
            $approversId[] = $approver['approver_id'];
        }

        $isRequesterApprover = in_array($user->id, $approversId);
        $requesterLayer = array_search($user->id, $approversId) + 1;
        $movementApproval = null;

        foreach ($approverIds as $approval) {
            $approverId = $approval['id'];
            $layer = $approval['layer'];

            $status = null;

            if ($isRequesterApprover) {
                if ($layer == $requesterLayer || $layer < $requesterLayer) {
                    $status = "Approved";
                } elseif ($layer == $requesterLayer + 1) {
                    $status = "For Approval";
                }
            } elseif ($layer == 1) { // if the requester is not an approver, only the first layer should be "For Approval"
                $status = "For Approval";
            }

            // Ensure the create method is called correctly
            $movementApproval = self::create([
                'movement_number_id' => $movementNumberId,
                'approver_id' => $approverId,
                'requester_id' => $user->id,
                'layer' => $layer,
                'status' => $status,
            ]);
        }

        return $movementApproval;
    }
}
