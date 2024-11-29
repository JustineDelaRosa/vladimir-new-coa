<?php

namespace App\Http\Requests\AssetPullOut;

use App\Rules\AssetMovementCheck;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetPullOutRequest extends FormRequest
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
            'assets.*.fixed_asset_id' => [new AssetMovementCheck(), 'required', 'exists:fixed_assets,id', function ($attribute, $value, $fail) {

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
        ];
    }

    function messages()
    {
        return [
            'assets.required' => 'Please select at least one asset.',
            'assets.*.fixed_asset_id.required' => 'Please select at least one asset.',
            'assets.*.fixed_asset_id.exists' => 'The selected asset does not exist.',
            'care_of.required' => 'Please select a care of.',
            'description.required' => 'Please enter a description.',
        ];
    }
}
