<?php

namespace App\Models;

use App\Filters\AssetApprovalFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;


class AssetApproval extends Model
{
    use HasFactory, Filterable;

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $guarded = [];
//    protected $primaryKey = 'id';

    protected static $logAttributes = ['*'];

    protected string $default_filters = AssetApprovalFilters::class;

    public function assetRequest()
    {
        return $this->belongsTo(AssetRequest::class, 'transaction_number', 'transaction_number');
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
