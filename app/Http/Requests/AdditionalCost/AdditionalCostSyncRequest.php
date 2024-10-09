<?php

namespace App\Http\Requests\AdditionalCost;

use Illuminate\Foundation\Http\FormRequest;

class AdditionalCostSyncRequest extends FormRequest
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
            'assetTag' => 'required|array',
            'assetTag.*.poNumber' => 'nullable',
            'assetTag.*.prNumber' => 'nullable',
            'assetTag.*.mirId' => 'nullable',
            'assetTag.*.wareHouseId' => 'nullable',
            'assetTag.*.acquisitionDate' => 'required|date',
            'assetTag.*.customerCode' => 'nullable',
            'assetTag.*.customerName' => 'nullable',
            'assetTag.*.itemCode' => 'nullable',
            'assetTag.*.itemDescription' => 'required',
            'assetTag.*.uom' => 'required|exists:unit_of_measures,uom_name',
            'assetTag.*.servedQuantity' => 'required|numeric',
            'assetTag.*.assetTag' => 'required',
            'assetTag.*.approveDate' => 'nullable',
            'assetTag.*.companyId' => 'required|exists:companies,id',
            'assetTag.*.businessUnitId' => 'required|exists:business_units,id',
            'assetTag.*.departmentId' => 'required|exists:departments,id',
            'assetTag.*.unitId' => 'required|exists:units,id',
            'assetTag.*.subUnitId' => 'required|exists:sub_units,id',
            'assetTag.*.locationId' => 'required|exists:locations,id',
            'assetTag.*.majorCategoryName' => 'required|string',
            'assetTag.*.minorCategoryName' => 'required|string',
        ];
    }

    function messages()
    {
        return[
            'assetTag.*.acquisitionDate.required' => 'The acquisition date field is required.',
            'assetTag.*.acquisitionDate.date' => 'The acquisition date must be a date.',
            'assetTag.*.itemDescription.required' => 'The item description field is required.',
            'assetTag.*.uom.required' => 'The uom field is required.',
            'assetTag.*.uom.exists' => 'The selected uom is invalid.',
            'assetTag.*.servedQuantity.required' => 'The served quantity field is required.',
            'assetTag.*.servedQuantity.numeric' => 'The served quantity must be a number.',
            'assetTag.*.assetTag.required' => 'The asset tag field is required.',
            'assetTag.*.companyId.required' => 'The company field is required.',
            'assetTag.*.companyId.exists' => 'The selected company is invalid.',
            'assetTag.*.businessUnitId.required' => 'The business unit field is required.',
            'assetTag.*.businessUnitId.exists' => 'The selected business unit is invalid.',
            'assetTag.*.departmentId.required' => 'The department field is required.',
            'assetTag.*.departmentId.exists' => 'The selected department is invalid.',
            'assetTag.*.unitId.required' => 'The unit field is required.',
            'assetTag.*.unitId.exists' => 'The selected unit is invalid.',
            'assetTag.*.subUnitId.required' => 'The subunit field is required.',
            'assetTag.*.subUnitId.exists' => 'The selected subunit is invalid.',
            'assetTag.*.locationId.required' => 'The location field is required.',
            'assetTag.*.locationId.exists' => 'The selected location is invalid.',
            'assetTag.*.majorCategoryName.required' => 'The major category name field is required.',
            'assetTag.*.majorCategoryName.string' => 'The major category name must be a string.',
            'assetTag.*.minorCategoryName.required' => 'The minor category name field is required.',
            'assetTag.*.minorCategoryName.string' => 'The minor category name must be a string.',
        ];
    }
}
