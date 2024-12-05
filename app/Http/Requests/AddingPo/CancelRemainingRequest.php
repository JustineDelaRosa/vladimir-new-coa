<?php

namespace App\Http\Requests\AddingPo;

use Illuminate\Foundation\Http\FormRequest;

class CancelRemainingRequest extends FormRequest
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
            'causer' => 'required',
            'reason' => 'required',
            'transaction_no' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'causer.required' => 'Causer is required',
            'reason.required' => 'Reason is required',
            'transaction_no.required' => 'Transaction number is required',
        ];
    }
}
