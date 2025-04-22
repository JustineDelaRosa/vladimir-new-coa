<?php

namespace App\Http\Requests\DepreciationDebitTaggin;

use Illuminate\Foundation\Http\FormRequest;

class CreateDepreciationDebitTaggingRequest extends FormRequest
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
            'depreciation_debit_id' => ['required', 'exists:account_titles,sync_id', 'array'],
//            'initial_debit_id' => ['required', 'exists:account_titles,sync_id'],
        ];
    }
}
