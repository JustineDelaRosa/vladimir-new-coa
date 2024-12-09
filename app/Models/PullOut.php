<?php

namespace App\Models;

use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PullOut extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia, Filterable;

    protected $table = 'pullouts';

    public function pulloutHistory()
    {
        return $this->morphMany(AssetMovementHistory::class, 'movementHistory');
    }

    public function pulloutItemApproval()
    {
        return $this->morphMany(MovementItemApproval::class, 'movementItem');
    }

    protected $guarded = [];

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id', 'id');
    }

    public function movementNumber()
    {
        return $this->belongsTo(MovementNumber::class, 'movement_id', 'id');
    }

    public function movementApproval()
    {
        return $this->hasManyThrough(MovementApproval::class,
            MovementNumber::class,
            'id',
            'movement_id',
            'movement_id',
            'id');
    }
}
