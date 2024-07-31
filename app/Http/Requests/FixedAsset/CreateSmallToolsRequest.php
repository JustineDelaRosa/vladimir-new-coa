<?php

namespace App\Http\Requests\FixedAsset;

use App\Models\FixedAsset;
use Illuminate\Foundation\Http\FormRequest;

class CreateSmallToolsRequest extends FormRequest
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
            'reference_number' => ['required', 'string', function ($attribute, $value, $fail) {
                $typeOfRequest = FixedAsset::with('typeOfRequest')->where('reference_number', $value)->first();
                if ($typeOfRequest->typeOfRequest->type_of_request_name !== 'Small Tools') {
                    $fail('The selected item is not a small tools');
                }
            }],
            'inclusion' => 'required|array',
        ];
    }
}
