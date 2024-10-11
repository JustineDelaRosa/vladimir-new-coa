<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovementNumber extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function movementApproval(): HasMany
    {
        return $this->hasMany(MovementApproval::class, 'movement_number_id', 'id');
    }
    public function requester(): belongsTo
    {
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }

    public function transfer(): HasMany
    {
        return $this->hasMany(Transfer::class, 'movement_id', 'id');
    }
    public function pullout(): HasMany
    {
        return $this->hasMany(Pullout::class, 'movement_id', 'id');
    }
    public function disposal(): HasMany
    {
        return $this->hasMany(Disposal::class, 'movement_id', 'id');
    }

    public static function createMovementNumber($movementTypeModel, $subUnitId, $approverIds)
    {
        $user = auth('sanctum')->user();

        $approversId = [];
        $layer = [];
        foreach ($approverIds as $approver) {
            $approversId[] = $approver['approver_id'];
            $layer[] = $approver['layer'];
        }

        $isRequesterApprover = in_array($user->id, $approversId);
        $isLastApprover = false;
        $requesterLayer = 0;
        if ($isRequesterApprover) {
            $requesterLayer = array_search($user->id, $approversId) + 1;
            $maxLayer = max($layer);
            $isLastApprover = $maxLayer == $requesterLayer;
        }


        $movementNumber = self::create([
            'status' => $isLastApprover
                ? 'Approved'
                : ($isRequesterApprover
                    ? 'For Approval of Approver ' . ($requesterLayer + 1)
                    : 'For Approval of Approver 1'),
            'is_fa_approved' => false,
            'requester_id' => auth('sanctum')->user()->id,
        ]);
        (new MovementApproval())->createMovementApproval($movementNumber->id, $movementTypeModel, $subUnitId, $approverIds);

//            return [$isRequesterApprover, $isLastApprover, $requesterLayer];


        return $movementNumber;
    }
}
