<?php

namespace App\Http\Requests\AssetRelease;

use Illuminate\Foundation\Http\FormRequest;

class MultipleReleaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
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
            'warehouse_number_id' => ['bail','required', 'exists:warehouse_numbers,id', 'distinct', 'array'],
            'accountability' => ['required', 'string', 'in:Common,Personal Issued'],
            'accountable' => ['required_if:accountability,Personal Issued', 'string'],
            'received_by' => ['required', 'string'],
        ];
    }

    function messages(): array
    {
        return [
            'warehouse_number_id.required' => 'Warehouse Number is required',
            'warehouse_number_id.exists' => 'Warehouse Number does not exist',
            'warehouse_number_id.distinct' => 'Warehouse Number must be unique',
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
