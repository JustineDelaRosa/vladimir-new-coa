<?php

namespace App\Rules;

use App\Models\Disposal;
use App\Models\FixedAsset;
use App\Models\MovementNumber;
use App\Models\PullOut;
use App\Models\Transfer;
use Illuminate\Contracts\Validation\Rule;

class AssetMovementCheck implements Rule
{

    private string $errorMessage;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $assetTransferRequest = MovementNumber::where('is_received', 0)
            ->whereHas('transfer', function ($query) use ($value) {
                $query->where('fixed_asset_id', $value);
            })->orWhereHas('pullout', function($query) use ($value){
                $query->where('fixed_asset_id', $value);
            })->first();
//            ->orWhereHas('disposal', function ($query) use ($value) {
//            $query->where('fixed_asset_id', $value);
//        })

        $tagNumber = FixedAsset::where('id', $value)->first()->vladimir_tag_number;

        if($assetTransferRequest){
            $this->errorMessage = 'The fixed asset '.$tagNumber.' has a pending movement transaction';
            return false;
        }
        return true;

        //check if the fixed asset is not yet finished transfer or pullout or disposal
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->errorMessage;
    }
}
