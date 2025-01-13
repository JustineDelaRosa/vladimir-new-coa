<?php

namespace App\Models;

use App\Filters\AuthorizedTransferReceiverFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthorizedTransferReceiver extends Model
{
    use HasFactory, softDeletes, Filterable;


    protected $guarded = [];

    protected string $default_filters = AuthorizedTransferReceiverFilters::class;


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
