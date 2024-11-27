<?php

namespace App\Http\Requests\AssetPullOut;

use App\Rules\AssetMovementCheck;
use Illuminate\Foundation\Http\FormRequest;

class CreateAssetPullOutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'assets' => 'required|array',
            'assets.*.fixed_asset_id' => [new AssetMovementCheck(),'required', 'exists:fixed_assets,id', function ($attribute, $value, $fail) {

                // Get all fixed_asset_id values
                $fixedAssetIds = array_column($this->input('assets'), 'fixed_asset_id');

                // Check if there's a duplicate
                if (count($fixedAssetIds) !== count(array_unique($fixedAssetIds))) {
                    $fail('Duplicate fixed asset found');
                }
            }],
            'care_of' => 'required',
            'remarks' => 'nullable',
            'description' => 'required',
//            'attachments' => 'nullable|max:5120',
        ];
    }
}
