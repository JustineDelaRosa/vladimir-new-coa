<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FistoTransaction extends Model
{
    use HasFactory;

    protected $connection = 'fisto_db';
    protected $table = 'transactions';

//    public function pobatch(){
//        return $this->belongsTo(PoBatch::class, 'request_id', 'id');
//    }
}
