<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
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
            'warehouse_name' => 'required|string|unique:warehouses,warehouse_name',
        ];
    }

    public function messages()
    {
        return [
            'warehouse_name.required' => 'Warehouse name is required.',
            'warehouse_name.string' => 'Warehouse name must be a string.',
            'warehouse_name.unique' => 'Warehouse name already exists.'
        ];
    }
}
