<?php

namespace App\Models;

use App\Filters\AssetTransferApproverFilters;
use App\Filters\AssetTransferRequestFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferApproval extends Model
{
    use HasFactory, Filterable;

    protected $guarded = [];

    protected string $default_filters = AssetTransferApproverFilters::class;

    public function transferRequest()
    {
        return $this->belongsTo(AssetTransferRequest::class, 'transfer_number', 'transfer_number');
    }

    public function approver()
    {
        return $this->belongsTo(Approvers::class, 'approver_id', 'id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }
}
