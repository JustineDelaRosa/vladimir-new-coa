<?php

namespace App\Http\Requests\AddingPo;

use App\Models\AssetRequest;
use App\Rules\UniqueWithIgnore;
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
        $transactionNumber = AssetRequest::where('id', $id)->first()->transaction_number;
        // dd($transactionNumber);
        return [
            "po_number" => ['required', new UniqueWithIgnore('fixed_assets', $id, $transactionNumber, $this->supplier_id)],
            "rr_number" => ['required', 'string', new UniqueWithIgnore('fixed_assets', $id, $transactionNumber)],
            "supplier_id" => "required|integer|exists:suppliers,id,is_active,1",
            "delivery_date" => "required|date",
            "quantity_delivered" => [
                "required", "integer", "min:1",
                function ($attribute, $value, $fail) use ($id) {
                    $assetRequest = AssetRequest::where('id', $id)->first();
                    if ($assetRequest == null) {
                        $fail("Asset Request does not exist");
                        return;
                    }
                    if ($assetRequest->quantity < ($value + $assetRequest->quantity_delivered)) {
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
            "quantity_delivered.required" => "Quantity Delivered is required",
            "quantity_delivered.integer" => "Quantity Delivered must be an integer",
            "unit_price.required" => "Unit Price is required",
            "unit_price.numeric" => "Unit Price must be a number",
        ];
    }
}
