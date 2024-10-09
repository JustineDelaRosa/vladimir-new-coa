<?php

namespace App\Http\Requests\AccountingEntries;

use Illuminate\Foundation\Http\FormRequest;

class CreateAccountingEntriesRequest extends FormRequest
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
            'initial_debit_id' => 'required|exists:account_titles,id',
            'initial_credit_id' => 'required|exists:account_titles,id',
            'depreciation_debit_id' => 'required|exists:account_titles,id',
            'depreciation_credit_id' => 'required|exists:account_titles,id',
        ];
    }

    public function messages()
    {
        return [
            'initial_debit_id.required' => 'The initial debit field is required.',
            'initial_debit_id.exists' => 'The selected initial debit is invalid.',
            'initial_credit_id.required' => 'The initial credit field is required.',
            'initial_credit_id.exists' => 'The selected initial credit is invalid.',
            'depreciation_debit_id.required' => 'The depreciation debit field is required.',
            'depreciation_debit_id.exists' => 'The selected depreciation debit is invalid.',
            'depreciation_credit_id.required' => 'The depreciation credit field is required.',
            'depreciation_credit_id.exists' => 'The selected depreciation credit is invalid.',
        ];
    }
}
