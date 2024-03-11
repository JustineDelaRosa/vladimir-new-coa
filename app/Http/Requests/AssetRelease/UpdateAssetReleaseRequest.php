<?php

namespace App\Http\Requests\AssetRelease;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetReleaseRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'accountability' => ['required', 'string', 'in:Common,Personal Issued'],
            'accountable' => ['required_if:accountability,Personal Issued', 'string'],
            'received_by' => ['required', 'string'],
        ];
    }

    function messages(): array
    {
        return [
            'accountability.required' => 'Accountability is required',
            'accountability.string' => 'Accountability must be a string',
            'accountability.in' => 'Accountability must be either Common or Personal Issued',
            'accountable.required_if' => 'Accountable is required',
            'accountable.string' => 'Accountable must be a string',
            'received_by.required' => 'Received By is required',
            'received_by.string' => 'Received By must be a string',
        ];
    }
}
