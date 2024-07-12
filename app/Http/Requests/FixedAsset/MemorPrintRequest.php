<?php

namespace App\Http\Requests\FixedAsset;

use Illuminate\Foundation\Http\FormRequest;

class MemorPrintRequest extends FormRequest
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
            "fixed_asset_id" => ["required", "exists:fixed_assets,vladimir_tag_number"],
        ];
    }

    function messages()
    {
        return [
            "fixed_asset_id.required" => "Fixed Asset is required",
            "fixed_asset_id.exists" => "Fixed Asset does not exist",
        ];
    }
}
