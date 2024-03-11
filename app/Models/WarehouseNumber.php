<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WarehouseNumber extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function generateWhNumber()
    {
        try {
            DB::transaction(function () {
//                // Ensure the model has been saved and has an ID
                if ($this->id === null) {
                    $this->save();
                }
                $warehouseNumber = $this->id;
                // Use the ID as the reference number
                $this->warehouse_number = str_pad($warehouseNumber, 4, '0', STR_PAD_LEFT);

                // Save the model again to store the reference number
                $this->save();
            });

            return $this->warehouse_number;
        } catch (Exception $e) {
            // Handle exception if necessary
            return null;
        }
    }
}
