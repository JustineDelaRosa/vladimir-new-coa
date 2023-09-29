<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class AssetApproval extends Model
{
    use HasFactory;



    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $guarded = [];
//    protected $primaryKey = 'id';

    public function assetRequest()
    {
        return $this->belongsTo(AssetRequest::class , 'asset_request_id' , 'id');
    }

    public function approver()
    {
        return $this->belongsTo(Approvers::class , 'approver_id' , 'id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class , 'requester_id' , 'id');
    }
}
