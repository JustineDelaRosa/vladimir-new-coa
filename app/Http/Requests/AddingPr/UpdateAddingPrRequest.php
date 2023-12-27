<?php

namespace App\Http\Requests\AddingPr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddingPrRequest extends FormRequest
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
        // . $this->transactionNumber . ',transaction_number'
        return [
            'pr_number' => 'required|string|unique:asset_requests,pr_number,',
            'business_unit_id' => 'required|exists:companies,id',
        ];
    }

    function messages()
    {
        return [
            'pr_number.required' => 'PR Number is required',
            'pr_number.string' => 'Invalid data type',
            'pr_number.unique' => 'PR Number already exists',
            'business_unit_id.required' => 'Business Unit is required',
            'business_unit_id.exists' => 'Business Unit does not exist',
        ];
    }
}
