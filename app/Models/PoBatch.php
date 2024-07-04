<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoBatch extends Model
{
    use HasFactory;

    protected $connection = 'fisto_db';
    protected $table = 'p_o_batches';

    public function fistoTransaction(){
        return $this->hasOne(FistoTransaction::class, 'id', 'request_id');
    }
}
