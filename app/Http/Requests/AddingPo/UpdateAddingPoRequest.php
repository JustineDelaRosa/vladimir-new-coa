<?php

namespace App\Http\Requests\AddingPO;

use App\Models\AssetRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAddingPoRequest extends FormRequest
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
        //get the id
        $id = $this->route('adding_po');
        return [
            "po_number" => "required|string|unique:asset_requests,po_number,{$id}",
            "rr_number" => "required|string|unique:asset_requests,rr_number,{$id}",
            "supplier_id" => "required|integer|exists:suppliers,id,is_active,1",
            "delivery_date" => "required|date",
            "quantity_delivered" => [
                "required", "integer",
                function ($attribute, $value, $fail) use ($id) {
                    $assetRequest = AssetRequest::where('id', $id)->first();
                    if ($assetRequest == null) {
                        $fail("Asset Request does not exist");
                        return;
                    }
                    if ($assetRequest->quantity < $value) {
                        $fail("Too many quantity delivered for this");
                    }
                }
            ],
            "unit_price" => "required|numeric",
        ];
    }

    public function messages()
    {
        return [
            "po_number.required" => "PO Number is required",
            "po_number.string" => "PO Number must be a string",
            "rr_number.required" => "RR Number is required",
            "rr_number.string" => "RR Number must be a string",
            "supplier_id.required" => "Supplier is required",
            "supplier_id.integer" => "Supplier must be an integer",
            "supplier_id.exists" => "Supplier must be an existing supplier",
            "delivery_date.required" => "Delivery Date is required",
            "delivery_date.date" => "Delivery Date must be a date",
            "quatity_delivered.required" => "Quantity Delivered is required",
            "quatity_delivered.integer" => "Quantity Delivered must be an integer",
            "unit_price.required" => "Unit Price is required",
            "unit_price.numeric" => "Unit Price must be a number",
        ];
    }
}
